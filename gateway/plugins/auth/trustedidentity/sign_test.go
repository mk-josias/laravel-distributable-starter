package trustedidentity

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"strings"
	"testing"
	"time"
)

func TestItSignsTheClaimsWithTheirExpiry(t *testing.T) {
	signed, err := Sign(map[string]any{"sub": "42", "permissions": []string{"analytics.datasets.read"}}, time.Unix(1790000000, 0), time.Minute, "secret")
	if err != nil {
		t.Fatal(err)
	}

	payload, signature, _ := strings.Cut(signed, ".")
	mac := hmac.New(sha256.New, []byte("secret"))
	mac.Write([]byte(payload))
	if signature != hex.EncodeToString(mac.Sum(nil)) {
		t.Fatalf("signature %q", signature)
	}

	decoded, _ := base64.RawURLEncoding.DecodeString(payload)
	if string(decoded) != `{"exp":1790000060,"permissions":["analytics.datasets.read"],"sub":"42"}` {
		t.Fatalf("payload %s", decoded)
	}

	var claims map[string]any
	if json.Unmarshal(decoded, &claims) != nil {
		t.Fatal("not JSON")
	}
}
