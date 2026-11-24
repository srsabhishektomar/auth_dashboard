# Auth Dashboard implementation plan

## Architecture

1. Use the stable, Drupal 11-compatible OpenID Connect contributed module (Composer lock: `drupal/openid_connect` 1.6.0) with its Generic client for Auth0 authorization-code login and account linkage.
2. Declare the Auth0-specific contributed module as an additional dependency per user request; Drupal enables it with the custom module. Its `/user/login` flow is separate from the Generic OIDC role-mapping flow.
3. Create `member` and `exhibitor` Drupal roles during module installation.
4. Read the configured namespaced Auth0 role claim, map it to Drupal roles, and remove stale prototype dashboard roles.
5. Redirect the account after login according to the assigned role, after OpenID Connect finishes role mapping.
6. Use route-level `_role` requirements for anonymous and cross-role URL protection.
7. Provide `/auth/login` and a CSRF-protected `/auth/logout` that clears the Drupal session and performs Auth0 logout.
8. Use server-side Guzzle calls for JSONPlaceholder and the public SOAP Country Information service.

## Implementation status

The module includes module metadata, role configuration, login/logout and dashboard routes, menu entries, configuration schema, role-claim mapping hooks, a post-callback redirect subscriber, the dashboard controller, and external API service.

The Generic OIDC provider requires the tenant's client ID and secret. The Auth0 module's settings are separately synchronized from those provider settings for its own routes; its cookie secret is generated locally. Keep credentials out of source control and use Drupal Key for production secrets. The Auth0 Post Login Action must emit the configured namespaced claim for the Generic OIDC role-mapping flow.

## Review decisions

- The custom module owns role mapping and dashboards, not provider credentials.
- Route access is enforced by Drupal route requirements in addition to UI links.
- Auth0 logout is confirmed with Drupal Form API before the local session is cleared and the browser is redirected to Auth0.
- API failures are logged without exposing provider internals.
- JSONPlaceholder and Beeceptor SOAP remain public, unauthenticated demonstration integrations.

## Contributed authentication module decision

OpenID Connect remains the primary integration, selected over relying solely on the Auth0-specific module or Social Auth. Its stable `8.x-1.6` release supports Drupal 11 and Composer already locks it. The Generic OIDC client fits Auth0's standard protocol and supports first-login account creation plus standard profile-claim mapping. The Auth0-specific module is also enabled as requested and owns `/user/login`, `/user/logout`, and `/auth0/callback`.

The Generic client requires manually configured authorization, token, and UserInfo endpoints; it does not consume OIDC discovery metadata directly. It also has no built-in mapping for Auth0 custom role claims and no Auth0 remote-logout flow. The custom module therefore owns claim-to-role mapping, role-based dashboards/redirects, and an Auth0 logout redirect. The Auth0-specific module's current Drupal 11 line is alpha and minimally maintained; its regional-domain validator and PHP 8.4 nullable signatures have local fixes. Its Auth0 callback URL (`/auth0/callback`) must also be allowed in the Auth0 application if `/user/login` is used. Social Auth has no Auth0 connector in its listed providers and would require one.

