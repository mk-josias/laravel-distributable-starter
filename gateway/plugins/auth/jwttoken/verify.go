// Package jwttoken checks a JWT iam signed, as Foundation\Iam\Auth\JwtTokens does.
package jwttoken

import (
	"crypto"
	"crypto/rsa"
	"crypto/sha256"
	"crypto/x509"
	"encoding/base64"
	"encoding/json"
	"encoding/pem"
	"errors"
	"strconv"
	"strings"
	"time"
)

// PublicKey reads iam's RSA public key from its PEM.
func PublicKey(pemKey string) (*rsa.PublicKey, error) {
	block, _ := pem.Decode([]byte(pemKey))
	if block == nil {
		return nil, errors.New("jwttoken: the public key is not a PEM")
	}

	parsed, err := x509.ParsePKIXPublicKey(block.Bytes)
	if err != nil {
		return nil, err
	}

	key, ok := parsed.(*rsa.PublicKey)
	if !ok {
		return nil, errors.New("jwttoken: the public key is not an RSA key")
	}

	return key, nil
}

// IsJwt tells a JWT from an opaque token: three dot-separated parts.
func IsJwt(token string) bool {
	return strings.Count(token, ".") == 2
}

// Claims returns the claims of a token signed in RS256 by key and not expired at now, and its sub.
func Claims(key *rsa.PublicKey, token string, now time.Time) (map[string]any, string, bool) {
	parts := strings.Split(token, ".")
	if len(parts) != 3 {
		return nil, "", false
	}

	signature, err := base64.RawURLEncoding.DecodeString(parts[2])
	if err != nil {
		return nil, "", false
	}

	digest := sha256.Sum256([]byte(parts[0] + "." + parts[1]))
	if rsa.VerifyPKCS1v15(key, crypto.SHA256, digest[:], signature) != nil {
		return nil, "", false
	}

	payload, err := base64.RawURLEncoding.DecodeString(parts[1])
	if err != nil {
		return nil, "", false
	}

	var claims map[string]any
	if json.Unmarshal(payload, &claims) != nil {
		return nil, "", false
	}

	exp, _ := claims["exp"].(float64)
	if int64(exp) < now.Unix() {
		return nil, "", false
	}

	sub := ""
	switch value := claims["sub"].(type) {
	case string:
		sub = value
	case float64:
		sub = strconv.FormatInt(int64(value), 10)
	}

	return claims, sub, sub != ""
}
