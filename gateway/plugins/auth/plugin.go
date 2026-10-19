// Package gatewayauth is a Traefik middleware that authenticates each request once, at the gateway,
// and sends it to the module with the X-Identity the modules trust.
//
// The strategy is AUTH_TOKEN_VALIDATION_STRATEGY, the one iam issues its tokens for:
//
//	signature → a JWT, checked with its issuer's public key → the user read in gateway-cache → its claims signed here
//	          → on a miss, iam over RPC (FindUserByToken), which writes the user there; the claims are signed here
//	          → with checkUser false (an issuer outside iam), its claims signed here, no lookup
//	issuer    → an opaque token: iam over RPC (FindUserByToken), on every request, so a revoked token stops at once
package gatewayauth

import (
	"context"
	"crypto/rsa"
	"errors"
	"net/http"
	"os"
	"strings"
	"time"

	"github.com/mk-josias/gatewayauth/jwttoken"
	"github.com/mk-josias/gatewayauth/redis"
	"github.com/mk-josias/gatewayauth/rpc"
	"github.com/mk-josias/gatewayauth/trustedidentity"
	"github.com/mk-josias/gatewayauth/usercache"
)

// Config is the middleware's settings, from Traefik's dynamic configuration.
type Config struct {
	// signature or issuer.
	Strategy      string `json:"strategy,omitempty"`
	Redis         string `json:"redis,omitempty"`
	RedisUser     string `json:"redisUser,omitempty"`
	RedisPassword string `json:"redisPassword,omitempty"`
	// The key iam writes a user under, without the id.
	UserKey string `json:"userKey,omitempty"`
	// Where iam answers its messages over RPC, and the secret every module signs them with.
	Rpc       string `json:"rpc,omitempty"`
	RpcSecret string `json:"rpcSecret,omitempty"`
	// The issuer's public key: iam's, or an identity provider's (Keycloak, Auth0). Or the file holding it.
	PublicKey     string `json:"publicKey,omitempty"`
	PublicKeyFile string `json:"publicKeyFile,omitempty"`
	// False when the issuer is outside iam: the token's claims are all there is to know of the user.
	CheckUser     bool   `json:"checkUser,omitempty"`
	GatewaySecret string `json:"gatewaySecret,omitempty"`
	Header        string `json:"header,omitempty"`
	IdentityTTL   int    `json:"identityTtl,omitempty"`
}

// CreateConfig gives the starter's defaults.
func CreateConfig() *Config {
	return &Config{Strategy: Signature, UserKey: "gateway:user:", CheckUser: true, Header: "X-Identity", IdentityTTL: 60}
}

// The two strategies.
const (
	Signature = "signature"
	Issuer    = "issuer"
)

type auth struct {
	next   http.Handler
	config *Config
	key    *rsa.PublicKey
	users  usercache.Reader
	iam    rpc.Client
}

// New builds the middleware.
func New(_ context.Context, next http.Handler, config *Config, _ string) (http.Handler, error) {
	if config.Strategy != Signature && config.Strategy != Issuer {
		return nil, errors.New("gatewayauth: strategy is signature or issuer, not " + config.Strategy)
	}

	// Under issuer, iam checks every token: there is no key to read.
	var key *rsa.PublicKey
	if config.Strategy == Signature {
		pem := config.PublicKey
		if pem == "" && config.PublicKeyFile != "" {
			read, err := os.ReadFile(config.PublicKeyFile)
			if err != nil {
				return nil, err
			}
			pem = string(read)
		}

		parsed, err := jwttoken.PublicKey(pem)
		if err != nil {
			return nil, err
		}
		key = parsed
	}

	server := redis.Server{Address: config.Redis, User: config.RedisUser, Password: config.RedisPassword, Timeout: time.Second}

	return &auth{
		next:   next,
		config: config,
		key:    key,
		users:  usercache.Reader{Server: server},
		iam:    rpc.Client{Host: config.Rpc, Secret: config.RpcSecret, HTTP: &http.Client{Timeout: 3 * time.Second}},
	}, nil
}

func (a *auth) ServeHTTP(rw http.ResponseWriter, req *http.Request) {
	// The client's own X-Identity is never trusted.
	req.Header.Del(a.config.Header)

	identity := a.identity(req.Header.Get("Authorization"))
	if identity == "" {
		rw.WriteHeader(http.StatusUnauthorized)
		return
	}

	req.Header.Set(a.config.Header, identity)
	a.next.ServeHTTP(rw, req)
}

func (a *auth) identity(authorization string) string {
	token := strings.TrimPrefix(authorization, "Bearer ")
	if token == "" || token == authorization {
		return ""
	}

	if a.config.Strategy == Issuer {
		user, ok := a.askIam(token)
		if !ok {
			return ""
		}

		return a.sign(map[string]any{"sub": user["id"], "name": user["name"]})
	}

	if !jwttoken.IsJwt(token) {
		return ""
	}

	claims, id, ok := jwttoken.Claims(a.key, token, time.Now())
	if !ok {
		return ""
	}

	var user map[string]any
	if a.config.CheckUser && !a.users.Read(a.config.UserKey+id, &user) {
		if _, ok := a.askIam(token); !ok {
			return ""
		}
	}

	return a.sign(claims)
}

// askIam asks iam which user a token opens, with the message FindUserByToken; false when it opens nothing.
func (a *auth) askIam(token string) (map[string]any, bool) {
	return a.iam.Call("iam", 1, "find-user-by-token", map[string]any{"token": token}, time.Now())
}

func (a *auth) sign(claims map[string]any) string {
	identity, err := trustedidentity.Sign(claims, time.Now(), time.Duration(a.config.IdentityTTL)*time.Second, a.config.GatewaySecret)
	if err != nil {
		return ""
	}

	return identity
}
