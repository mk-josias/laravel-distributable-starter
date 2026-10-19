package rpc

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"io"
	"net/http"
	"net/http/httptest"
	"strconv"
	"strings"
	"testing"
	"time"
)

func serve(t *testing.T, handle func(rw http.ResponseWriter, req *http.Request, body string)) Client {
	server := httptest.NewServer(http.HandlerFunc(func(rw http.ResponseWriter, req *http.Request) {
		body, _ := io.ReadAll(req.Body)
		handle(rw, req, string(body))
	}))
	t.Cleanup(server.Close)

	return Client{Host: server.URL, Secret: "secret", HTTP: &http.Client{Timeout: time.Second}}
}

func TestACallIsSignedAsTheCalledSideChecksIt(t *testing.T) {
	now := time.Unix(1_700_000_000, 0)
	client := serve(t, func(rw http.ResponseWriter, req *http.Request, body string) {
		timestamp := req.Header.Get("X-Rpc-Timestamp")
		mac := hmac.New(sha256.New, []byte("secret"))
		mac.Write([]byte(strings.Join([]string{timestamp, req.Header.Get("X-Rpc-Nonce"), req.URL.Path, body, req.Header.Get("X-Rpc-Context")}, "\n")))

		if timestamp != strconv.FormatInt(now.Unix(), 10) || req.Method != http.MethodPost ||
			req.URL.Path != "/iam/rpc/1/find-user-by-token" || body != `{"payload":{"token":"abc"}}` ||
			req.Header.Get("X-Rpc-Signature") != hex.EncodeToString(mac.Sum(nil)) {
			rw.WriteHeader(http.StatusForbidden)
			return
		}

		rw.Write([]byte(`{"id":42,"name":"Ada"}`))
	})

	answer, ok := client.Call("iam", 1, "find-user-by-token", map[string]any{"token": "abc"}, now)

	if !ok || answer["id"] != float64(42) || answer["name"] != "Ada" {
		t.Fatalf("ok %v, answer %v", ok, answer)
	}
}

func TestEveryCallHasItsOwnNonce(t *testing.T) {
	nonces := map[string]bool{}
	client := serve(t, func(rw http.ResponseWriter, req *http.Request, _ string) {
		nonces[req.Header.Get("X-Rpc-Nonce")] = true
		rw.Write([]byte(`{}`))
	})

	client.Call("iam", 1, "find-user-by-token", nil, time.Now())
	client.Call("iam", 1, "find-user-by-token", nil, time.Now())

	if len(nonces) != 2 {
		t.Fatalf("%d distinct nonces for 2 calls", len(nonces))
	}
}

func TestWhatIsNotAnAnswerIsNotOk(t *testing.T) {
	for name, status := range map[string]int{"null": http.StatusNotFound, "refused": http.StatusForbidden, "failed": http.StatusInternalServerError} {
		client := serve(t, func(rw http.ResponseWriter, _ *http.Request, _ string) { rw.WriteHeader(status) })

		if _, ok := client.Call("iam", 1, "find-user-by-token", nil, time.Now()); ok {
			t.Fatalf("%s: ok", name)
		}
	}
}

func TestAModuleThatCannotBeReachedIsNotOk(t *testing.T) {
	client := Client{Host: "http://127.0.0.1:1", Secret: "secret", HTTP: &http.Client{Timeout: time.Second}}

	if _, ok := client.Call("iam", 1, "find-user-by-token", nil, time.Now()); ok {
		t.Fatal("ok")
	}
}
