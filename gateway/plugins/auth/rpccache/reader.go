// Package rpccache reads the answers an RpcService keeps, as RpcService::read() does: the JSON under
// {REDIS_PREFIX}{CACHE_PREFIX}{key}, in the database of Laravel's cache connection.
package rpccache

import (
	"encoding/json"

	"github.com/mk-josias/gatewayauth/redis"
)

// Reader reads the cache of one store.
type Reader struct {
	Server redis.Server
	// REDIS_PREFIX then CACHE_PREFIX: database:cache:
	Prefix string
}

// Read returns the answer kept under key, decoded; a missing or unreadable one is not found.
func (r Reader) Read(key string, answer any) bool {
	value, found, err := r.Server.Get(r.Prefix + key)

	return err == nil && found && json.Unmarshal([]byte(value), answer) == nil
}
