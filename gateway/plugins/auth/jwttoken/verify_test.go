package jwttoken

import (
	"crypto"
	"crypto/rand"
	"crypto/rsa"
	"crypto/sha256"
	"crypto/x509"
	"encoding/base64"
	"encoding/pem"
	"fmt"
	"testing"
	"time"
)

func sign(key *rsa.PrivateKey, sub string, exp int64) string {
	encode := base64.RawURLEncoding.EncodeToString
	unsigned := encode([]byte(`{"alg":"RS256","typ":"JWT"}`)) + "." + encode([]byte(fmt.Sprintf(`{"sub":"%s","exp":%d}`, sub, exp)))
	digest := sha256.Sum256([]byte(unsigned))
	signature, _ := rsa.SignPKCS1v15(rand.Reader, key, crypto.SHA256, digest[:])

	return unsigned + "." + encode(signature)
}

func TestItReadsTheSubjectOfAValidToken(t *testing.T) {
	key, _ := rsa.GenerateKey(rand.Reader, 2048)
	der, _ := x509.MarshalPKIXPublicKey(&key.PublicKey)
	public, err := PublicKey(string(pem.EncodeToMemory(&pem.Block{Type: "PUBLIC KEY", Bytes: der})))
	if err != nil {
		t.Fatal(err)
	}

	claims, sub, ok := Claims(public, sign(key, "42", time.Now().Unix()+60), time.Now())
	if !ok || sub != "42" || claims["sub"] != "42" {
		t.Fatalf("sub %q, ok %v, claims %v", sub, ok, claims)
	}
}

func TestItRefusesAnExpiredOrForeignToken(t *testing.T) {
	key, _ := rsa.GenerateKey(rand.Reader, 2048)
	other, _ := rsa.GenerateKey(rand.Reader, 2048)

	for name, token := range map[string]string{
		"expired":     sign(key, "42", time.Now().Unix()-1),
		"another key": sign(other, "42", time.Now().Unix()+60),
		"not a jwt":   "abc",
	} {
		if _, _, ok := Claims(&key.PublicKey, token, time.Now()); ok {
			t.Fatalf("%s: accepted", name)
		}
	}
}

func TestItTellsAJwtFromAnOpaqueToken(t *testing.T) {
	if !IsJwt("a.b.c") || IsJwt("9f3c0e2a") {
		t.Fatal("misread")
	}
}
