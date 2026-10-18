package rpccache

import (
	"testing"
	"time"

	"github.com/mk-josias/gatewayauth/redis"
	"github.com/mk-josias/gatewayauth/redis/redistest"
)

func reader(t *testing.T, keys map[string]string) Reader {
	address := redistest.Serve(t, keys)

	return Reader{Server: redis.Server{Address: address, User: "traefik", Database: 1, Timeout: time.Second}, Prefix: "database:cache:"}
}

func TestItReadsAnAnswerAsTheRpcServiceWroteIt(t *testing.T) {
	var user struct {
		ID   int    `json:"id"`
		Name string `json:"name"`
	}

	found := reader(t, map[string]string{"1:database:cache:iam:user:42": `{"id":42,"name":"Ada"}`}).Read("iam:user:42", &user)

	if !found || user.ID != 42 || user.Name != "Ada" {
		t.Fatalf("found %v, user %+v", found, user)
	}
}

func TestAMissingOrNonJsonAnswerIsNotFound(t *testing.T) {
	cache := reader(t, map[string]string{"1:database:cache:iam:user:7": `a:1:{s:2:"id";i:7;}`})
	var user map[string]any

	if cache.Read("iam:user:42", &user) || cache.Read("iam:user:7", &user) {
		t.Fatal("found")
	}
}
