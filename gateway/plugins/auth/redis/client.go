// Package redis is the least of a Redis client the gateway needs: AUTH, SELECT and GET, over RESP.
// Traefik runs plugins with Yaegi, which loads no client relying on unsafe.
package redis

import (
	"bufio"
	"errors"
	"fmt"
	"io"
	"net"
	"strconv"
	"strings"
	"time"
)

// Server is where to connect, and as whom.
type Server struct {
	Address  string
	User     string
	Password string
	Database int
	Timeout  time.Duration
}

// Get reads key; a missing key returns found false.
func (s Server) Get(key string) (value string, found bool, err error) {
	conn, err := net.DialTimeout("tcp", s.Address, s.Timeout)
	if err != nil {
		return "", false, err
	}
	defer conn.Close()

	_ = conn.SetDeadline(time.Now().Add(s.Timeout))
	reader := bufio.NewReader(conn)

	if s.User != "" {
		if _, _, err := do(conn, reader, "AUTH", s.User, s.Password); err != nil {
			return "", false, err
		}
	}

	if _, _, err := do(conn, reader, "SELECT", strconv.Itoa(s.Database)); err != nil {
		return "", false, err
	}

	return do(conn, reader, "GET", key)
}

// do sends one command and reads a simple string, a bulk string or a nil.
func do(conn net.Conn, reader *bufio.Reader, args ...string) (string, bool, error) {
	request := fmt.Sprintf("*%d\r\n", len(args))
	for _, arg := range args {
		request += fmt.Sprintf("$%d\r\n%s\r\n", len(arg), arg)
	}

	if _, err := conn.Write([]byte(request)); err != nil {
		return "", false, err
	}

	line, err := reader.ReadString('\n')
	if err != nil {
		return "", false, err
	}
	line = strings.TrimRight(line, "\r\n")

	switch {
	case strings.HasPrefix(line, "+"):
		return line[1:], true, nil
	case line == "$-1":
		return "", false, nil
	case strings.HasPrefix(line, "$"):
		size, err := strconv.Atoi(line[1:])
		if err != nil {
			return "", false, err
		}
		value := make([]byte, size+2)
		if _, err := io.ReadFull(reader, value); err != nil {
			return "", false, err
		}
		return string(value[:size]), true, nil
	default:
		return "", false, errors.New(line)
	}
}
