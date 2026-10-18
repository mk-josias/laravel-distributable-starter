// Package redistest serves a fake Redis for the tests: AUTH as traefik, SELECT, then GET from keys named "{database}:{key}".
package redistest

import (
	"bufio"
	"fmt"
	"net"
	"strings"
	"testing"
)

// Serve listens on a free port until the test ends, and returns its address.
func Serve(t *testing.T, keys map[string]string) string {
	listener, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { listener.Close() })

	go func() {
		for {
			conn, err := listener.Accept()
			if err != nil {
				return
			}
			go answer(conn, keys)
		}
	}()

	return listener.Addr().String()
}

func answer(conn net.Conn, keys map[string]string) {
	defer conn.Close()

	reader := bufio.NewReader(conn)
	database := "0"

	for {
		header, err := reader.ReadString('\n')
		if err != nil {
			return
		}

		var count int
		fmt.Sscanf(header, "*%d", &count)
		args := make([]string, 0, count)
		for i := 0; i < count; i++ {
			reader.ReadString('\n')
			arg, _ := reader.ReadString('\n')
			args = append(args, strings.TrimRight(arg, "\r\n"))
		}

		value, found := keys[database+":"+args[len(args)-1]]

		switch {
		case args[0] == "AUTH" && args[1] != "traefik":
			conn.Write([]byte("-WRONGPASS invalid username-password pair\r\n"))
		case args[0] == "AUTH":
			conn.Write([]byte("+OK\r\n"))
		case args[0] == "SELECT":
			database = args[1]
			conn.Write([]byte("+OK\r\n"))
		case !found:
			conn.Write([]byte("$-1\r\n"))
		default:
			conn.Write([]byte(fmt.Sprintf("$%d\r\n%s\r\n", len(value), value)))
		}
	}
}
