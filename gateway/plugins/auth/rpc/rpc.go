// Package rpc calls a module's message over HTTP, as Distributable\RpcHttp\HttpTransport does:
// POST {host}/{module}/rpc/{version}/{message} with {"payload": …}, and an HMAC over the timestamp, a
// nonce, the path, the body and the propagated context (Distributable\RpcHttp\RpcSignature).
package rpc

import (
	"bytes"
	"crypto/hmac"
	"crypto/rand"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"net/http"
	"strconv"
	"strings"
	"time"
)

// Client calls the messages of the modules behind Host, signing them with Secret.
type Client struct {
	Host   string
	Secret string
	HTTP   *http.Client
}

// Call sends a message and returns the module's answer; false when it answers null (404), refuses, or cannot be reached.
func (c Client) Call(module string, version int, message string, payload map[string]any, now time.Time) (map[string]any, bool) {
	path := fmt.Sprintf("/%s/rpc/%d/%s", module, version, message)

	body, err := json.Marshal(map[string]any{"payload": payload})
	if err != nil {
		return nil, false
	}

	raw := make([]byte, 16)
	if _, err := rand.Read(raw); err != nil {
		return nil, false
	}

	nonce := hex.EncodeToString(raw)
	timestamp := strconv.FormatInt(now.Unix(), 10)
	context := "{}"

	mac := hmac.New(sha256.New, []byte(c.Secret))
	mac.Write([]byte(strings.Join([]string{timestamp, nonce, path, string(body), context}, "\n")))

	request, err := http.NewRequest(http.MethodPost, strings.TrimRight(c.Host, "/")+path, bytes.NewReader(body))
	if err != nil {
		return nil, false
	}
	request.Header.Set("Content-Type", "application/json")
	request.Header.Set("Accept", "application/json")
	request.Header.Set("X-Rpc-Timestamp", timestamp)
	request.Header.Set("X-Rpc-Nonce", nonce)
	request.Header.Set("X-Rpc-Context", context)
	request.Header.Set("X-Rpc-Signature", hex.EncodeToString(mac.Sum(nil)))

	response, err := c.HTTP.Do(request)
	if err != nil {
		return nil, false
	}
	defer response.Body.Close()

	if response.StatusCode != http.StatusOK {
		return nil, false
	}

	var answer map[string]any
	if json.NewDecoder(response.Body).Decode(&answer) != nil {
		return nil, false
	}

	return answer, true
}
