// Package trustedidentity writes the X-Identity the modules check, as Foundation\Iam\Auth\TrustedIdentity:
// `{payload}.{hmac}`, the payload being the token's claims as base64url JSON, with `exp` set.
package trustedidentity

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"time"
)

// Sign returns the X-Identity of claims, valid until now plus ttl.
func Sign(claims map[string]any, now time.Time, ttl time.Duration, secret string) (string, error) {
	signed := make(map[string]any, len(claims)+1)
	for name, value := range claims {
		signed[name] = value
	}
	signed["exp"] = now.Add(ttl).Unix()

	encoded, err := json.Marshal(signed)
	if err != nil {
		return "", err
	}

	payload := base64.RawURLEncoding.EncodeToString(encoded)
	mac := hmac.New(sha256.New, []byte(secret))
	mac.Write([]byte(payload))

	return payload + "." + hex.EncodeToString(mac.Sum(nil)), nil
}
