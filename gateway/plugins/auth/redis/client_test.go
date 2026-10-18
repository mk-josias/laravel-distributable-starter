package redis

import (
	"testing"
	"time"

	"github.com/mk-josias/gatewayauth/redis/redistest"
)

func TestItReadsAKeyInTheDatabaseItSelects(t *testing.T) {
	address := redistest.Serve(t, map[string]string{"1:cache:iam:user:42": `{"id":42}`})
	server := Server{Address: address, User: "traefik", Password: "secret", Database: 1, Timeout: time.Second}

	value, found, err := server.Get("cache:iam:user:42")
	if err != nil || !found || value != `{"id":42}` {
		t.Fatalf("value %q, found %v, err %v", value, found, err)
	}

	if _, found, _ := server.Get("cache:iam:user:7"); found {
		t.Fatal("a missing key was found")
	}
}

func TestItReportsARefusal(t *testing.T) {
	address := redistest.Serve(t, map[string]string{})
	server := Server{Address: address, User: "intruder", Password: "x", Database: 1, Timeout: time.Second}

	if _, _, err := server.Get("cache:iam:user:42"); err == nil {
		t.Fatal("a refused AUTH went unnoticed")
	}
}
