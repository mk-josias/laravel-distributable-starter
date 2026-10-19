// Package usercache reads the users iam writes for the gateway: the JSON under gateway:user:{id}.
package usercache

import (
	"encoding/json"

	"github.com/mk-josias/gatewayauth/redis"
)

// Reader reads the users of one gateway-cache.
type Reader struct {
	Server redis.Server
}

// Read returns the user kept under key, decoded; a missing or unreadable one is not found.
func (r Reader) Read(key string, user any) bool {
	value, found, err := r.Server.Get(key)

	return err == nil && found && json.Unmarshal([]byte(value), user) == nil
}
