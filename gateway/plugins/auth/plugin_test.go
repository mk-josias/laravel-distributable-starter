package gatewayauth

import (
	"context"
	"crypto"
	"crypto/hmac"
	"crypto/rand"
	"crypto/rsa"
	"crypto/sha256"
	"crypto/x509"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"encoding/pem"
	"fmt"
	"io"
	"net/http"
	"net/http/httptest"
	"os"
	"strings"
	"sync/atomic"
	"testing"
	"time"

	"github.com/mk-josias/gatewayauth/redis/redistest"
)

type gateway struct {
	handler  http.Handler
	key      *rsa.PrivateKey
	identity *string
	asked    *atomic.Int32
}

// signedBy tells whether an RPC call carries the signature Distributable\RpcHttp\RpcSignature checks.
func signedBy(secret string, req *http.Request, body []byte) bool {
	mac := hmac.New(sha256.New, []byte(secret))
	mac.Write([]byte(strings.Join([]string{req.Header.Get("X-Rpc-Timestamp"), req.Header.Get("X-Rpc-Nonce"), req.URL.Path, string(body), req.Header.Get("X-Rpc-Context")}, "\n")))

	return req.Header.Get("X-Rpc-Signature") == hex.EncodeToString(mac.Sum(nil))
}

// newGateway serves the middleware against a fake Redis holding keys and a fake iam answering the message
// FindUserByToken: the user when status is 204, null (404) otherwise, and a refusal to any call badly signed.
func newGateway(t *testing.T, keys map[string]string, status int, configure ...func(*Config)) gateway {
	var asked atomic.Int32
	iam := httptest.NewServer(http.HandlerFunc(func(rw http.ResponseWriter, req *http.Request) {
		asked.Add(1)
		body, _ := io.ReadAll(req.Body)

		if req.URL.Path != "/iam/rpc/1/find-user-by-token" || !signedBy("rpc-secret", req, body) {
			rw.WriteHeader(http.StatusForbidden)
			return
		}
		if status != http.StatusNoContent {
			rw.WriteHeader(http.StatusNotFound)
			return
		}

		rw.Write([]byte(`{"id":42,"name":"Ada"}`))
	}))
	t.Cleanup(iam.Close)

	key, _ := rsa.GenerateKey(rand.Reader, 2048)
	der, _ := x509.MarshalPKIXPublicKey(&key.PublicKey)

	config := CreateConfig()
	config.Redis = redistest.Serve(t, keys)
	config.RedisUser = "traefik"
	config.Rpc = iam.URL
	config.RpcSecret = "rpc-secret"
	config.GatewaySecret = "secret"
	config.PublicKey = string(pem.EncodeToMemory(&pem.Block{Type: "PUBLIC KEY", Bytes: der}))
	for _, change := range configure {
		change(config)
	}

	identity := ""
	handler, err := New(context.Background(), http.HandlerFunc(func(_ http.ResponseWriter, req *http.Request) {
		identity = req.Header.Get("X-Identity")
	}), config, "auth")
	if err != nil {
		t.Fatal(err)
	}

	return gateway{handler: handler, key: key, identity: &identity, asked: &asked}
}

func (g gateway) jwt(sub string, exp int64) string {
	encode := base64.RawURLEncoding.EncodeToString
	unsigned := encode([]byte(`{"alg":"RS256","typ":"JWT"}`)) + "." + encode([]byte(fmt.Sprintf(`{"sub":"%s","exp":%d}`, sub, exp)))
	digest := sha256.Sum256([]byte(unsigned))
	signature, _ := rsa.SignPKCS1v15(rand.Reader, g.key, crypto.SHA256, digest[:])

	return unsigned + "." + encode(signature)
}

// claims decodes the X-Identity the module received.
func (g gateway) claims() map[string]any {
	payload, _, _ := strings.Cut(*g.identity, ".")
	decoded, _ := base64.RawURLEncoding.DecodeString(payload)
	var claims map[string]any
	_ = json.Unmarshal(decoded, &claims)

	return claims
}

func (g gateway) serve(token string) int {
	req := httptest.NewRequest(http.MethodGet, "/analytics/api/v1/users/42", nil)
	req.Header.Set("X-Identity", "42.9999999999.forged")
	if token != "" {
		req.Header.Set("Authorization", "Bearer "+token)
	}
	rec := httptest.NewRecorder()
	g.handler.ServeHTTP(rec, req)

	return rec.Code
}

const cached = "0:gateway:user:42"

func TestACachedUserPassesWithoutAskingIam(t *testing.T) {
	g := newGateway(t, map[string]string{cached: `{"id":42,"name":"Ada"}`}, http.StatusNoContent)

	if code := g.serve(g.jwt("42", time.Now().Unix()+60)); code != http.StatusOK || g.claims()["sub"] != "42" || g.asked.Load() != 0 {
		t.Fatalf("status %d, identity %q, iam asked %d times", code, *g.identity, g.asked.Load())
	}
}

func TestAnIssuerOutsideIamHasItsClaimsSignedWithoutALookup(t *testing.T) {
	g := newGateway(t, map[string]string{}, http.StatusUnauthorized, func(config *Config) { config.CheckUser = false })

	if code := g.serve(g.jwt("usr_7f3a", time.Now().Unix()+60)); code != http.StatusOK || g.claims()["sub"] != "usr_7f3a" || g.asked.Load() != 0 {
		t.Fatalf("status %d, claims %v, iam asked %d times", code, g.claims(), g.asked.Load())
	}
}

func TestItReadsThePublicKeyFromAFile(t *testing.T) {
	file := t.TempDir() + "/jwt-public.key"
	g := newGateway(t, map[string]string{cached: `{"id":42}`}, http.StatusNoContent, func(config *Config) {
		os.WriteFile(file, []byte(config.PublicKey), 0o644)
		config.PublicKey = ""
		config.PublicKeyFile = file
	})

	if code := g.serve(g.jwt("42", time.Now().Unix()+60)); code != http.StatusOK {
		t.Fatalf("status %d", code)
	}
}

func TestAMissAsksIamAndSignsTheClaimsOfTheTokenItself(t *testing.T) {
	g := newGateway(t, map[string]string{}, http.StatusNoContent)

	if code := g.serve(g.jwt("42", time.Now().Unix()+60)); code != http.StatusOK || g.claims()["sub"] != "42" || g.asked.Load() != 1 {
		t.Fatalf("status %d, claims %v, iam asked %d times", code, g.claims(), g.asked.Load())
	}
}

func TestUnderTheIssuerStrategyEveryTokenIsCheckedByIamWithNoKey(t *testing.T) {
	g := newGateway(t, map[string]string{cached: `{"id":42}`}, http.StatusNoContent, func(config *Config) {
		config.Strategy = Issuer
		config.PublicKey = ""
	})

	if code := g.serve("9f3c0e2a"); code != http.StatusOK || g.asked.Load() != 1 || g.claims()["sub"] != float64(42) || g.claims()["name"] != "Ada" {
		t.Fatalf("status %d, claims %v, iam asked %d times", code, g.claims(), g.asked.Load())
	}
}

func TestUnderTheSignatureStrategyAnOpaqueTokenIsRefused(t *testing.T) {
	g := newGateway(t, map[string]string{}, http.StatusNoContent)

	if code := g.serve("9f3c0e2a"); code != http.StatusUnauthorized || g.asked.Load() != 0 {
		t.Fatalf("status %d, iam asked %d times", code, g.asked.Load())
	}
}

func TestWhatIamRefusesIsRefused(t *testing.T) {
	signature := newGateway(t, map[string]string{}, http.StatusUnauthorized)
	issuer := newGateway(t, map[string]string{}, http.StatusUnauthorized, func(config *Config) { config.Strategy = Issuer })

	if code := signature.serve(signature.jwt("42", time.Now().Unix()+60)); code != http.StatusUnauthorized {
		t.Fatalf("a jwt not cached: status %d", code)
	}
	if code := issuer.serve("9f3c0e2a"); code != http.StatusUnauthorized {
		t.Fatalf("an opaque token: status %d", code)
	}
}

func TestAnUnknownStrategyStopsTraefikAtStart(t *testing.T) {
	config := CreateConfig()
	config.Strategy = "jwt"

	if _, err := New(context.Background(), http.NotFoundHandler(), config, "auth"); err == nil {
		t.Fatal("accepted")
	}
}

func TestAnInvalidJwtNeverReachesTheModule(t *testing.T) {
	g := newGateway(t, map[string]string{cached: `{"id":42}`}, http.StatusNoContent)
	other := newGateway(t, map[string]string{}, http.StatusNoContent)

	for name, token := range map[string]string{
		"no token":    "",
		"expired":     g.jwt("42", time.Now().Unix()-1),
		"another key": other.jwt("42", time.Now().Unix()+60),
	} {
		if code := g.serve(token); code != http.StatusUnauthorized || *g.identity != "" {
			t.Fatalf("%s: status %d, identity %q", name, code, *g.identity)
		}
	}
}
