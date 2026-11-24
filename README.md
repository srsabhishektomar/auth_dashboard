# Drupal 11 Auth0 dashboard prototype

## Environment

- Drupal 11.3
- PHP 8.4 in DDEV
- MariaDB 10.11
- DDEV 1.25.5
- OpenID Connect (Drupal contrib module) for Auth0/OpenID login

## DDEV setup

Start Docker, then run:

```bash
ddev start
ddev composer install
ddev drush site:install standard --account-name=admin --account-pass=admin
ddev drush pm:enable auth_dashboard
ddev drush cr
```

Open `https://drupal11-headless.ddev.site:8443`.

## Auth0 setup

Create a Regular Web Application in Auth0. Configure:

- Allowed Callback URLs: `https://drupal11-headless.ddev.site:8443/openid-connect/generic`, `https://drupal11-headless.ddev.site:8443/auth0/callback`
- Allowed Logout URLs: `https://drupal11-headless.ddev.site:8443/`
- Allowed Web Origins: `https://drupal11-headless.ddev.site:8443`

In Drupal, open **Configuration > Services > OpenID Connect** (`/admin/config/services/openid-connect`), enable the **Generic** client, and set the Authorization, Token, and UserInfo endpoints to the values published in `https://<AUTH0_DOMAIN>/.well-known/openid-configuration` (typically `https://<AUTH0_DOMAIN>/authorize`, `/oauth/token`, and `/userinfo`). The selected module's Generic client does not accept a discovery URL; copy the endpoint values from the metadata document. Use the callback URL shown by the provider form as the exact Allowed Callback URL in Auth0. Supply both modules' credentials using local Drupal settings overrides as described below, not through the admin forms.

The custom module declares `openid_connect` and `auth0` as dependencies, so Drupal enables both when `auth_dashboard` is installed. `/auth/login` uses the OpenID Connect Generic client and maps the namespaced role claim; the Auth0 module also provides `/user/login` and `/auth0/callback`. Its callback must therefore be included in the Auth0 Allowed Callback URLs if that login route is used. Both modules use the same Auth0 domain, client ID, and client secret; the Auth0 module additionally needs its own cookie secret. The Auth0 module's regional-domain validation and PHP 8.4 nullable-parameter declarations have local changes in its Composer-managed source; a Composer reinstall will overwrite those changes unless they are reapplied. Use `/auth/logout` for the custom OpenID Connect local/remote logout flow.

### Local credentials in Drupal settings

The generated `web/sites/default/settings.php` contains an optional include for `settings.local.php` near its end. Uncomment that include and create `web/sites/default/settings.local.php` for local-only overrides. Ensure this file is ignored by Git; never commit it or put real credentials in exported configuration.

Put the following in `settings.local.php`, replacing the placeholders. Use the Auth0 tenant domain without `https://` or a path. Generate a unique cookie secret (for example, `openssl rand -hex 32`).

```php
<?php

$auth0_domain = 'YOUR_AUTH0_TENANT_DOMAIN';
$auth0_client_id = 'YOUR_AUTH0_CLIENT_ID';
$auth0_client_secret = 'YOUR_AUTH0_CLIENT_SECRET';
$auth0_cookie_secret = 'GENERATE_A_UNIQUE_RANDOM_SECRET';

$config['openid_connect.settings.generic']['settings']['client_id'] = $auth0_client_id;
$config['openid_connect.settings.generic']['settings']['client_secret'] = $auth0_client_secret;

$config['auth0.settings']['auth0_domain'] = $auth0_domain;
$config['auth0.settings']['auth0_client_id'] = $auth0_client_id;
$config['auth0.settings']['auth0_client_secret'] = $auth0_client_secret;
$config['auth0.settings']['auth0_cookie_secret'] = $auth0_cookie_secret;
$config['auth0.settings']['auth0_client_secret_key'] = '';
$config['auth0.settings']['auth0_cookie_secret_key'] = '';
```

These settings override the corresponding active configuration at runtime; they do not enable the Generic client or configure its endpoints, which remain in the OpenID Connect admin form. The empty Auth0 Key IDs ensure the module uses the secrets from this local override rather than a configured Key entity. After changing the local settings, run `ddev drush cr`. If credentials were previously entered through an admin form, this override does not remove the old values from active configuration or existing exports; remove those stored values and rotate any secret that was committed. For production, use a managed secret store such as Drupal's Key module instead of a local settings file.

Create an Auth0 custom namespace claim named `roles` containing `member` or `exhibitor`. A sample Auth0 Action is:

```javascript
exports.onExecutePostLogin = async (event, api) => {
  const namespace = 'https://drupal11-headless.ddev.site';
  const roles = event.authorization?.roles || [];
  api.idToken.setCustomClaim(`${namespace}/roles`, roles);
};
```

In **User Management > Roles**, assign the `member` role to the member account and `exhibitor` to the exhibitor account. Add the Action to the **Login** flow and deploy it. The module's authorization hooks read the namespaced role claim from the decoded ID token and map it to the matching Drupal role.

Set both callback URLs above, the Allowed Logout URL `https://drupal11-headless.ddev.site:8443/`, and the Allowed Web Origin `https://drupal11-headless.ddev.site:8443`.

The custom module creates the `member` and `exhibitor` Drupal roles. Use `/auth/login` to begin Auth0 login and `/auth/logout` to confirm local and Auth0 logout. After the OIDC callback completes, Drupal roles are mapped from the namespaced claim and the user is sent to their dashboard. If callback role validation fails, Drupal falls back to its front page instead of returning the signed-in user to the anonymous-only `/auth/login` route. The callback rejects sign-ins without exactly one supported role. Direct dashboard requests are also role-protected. Supply the `https://drupal11-headless.ddev.site/roles` claim key as `auth_dashboard.settings.auth0_role_claim` if the site was installed before this setting was added.

## Validation

Local validation in DDEV confirmed:

- The member controller retrieved the JSONPlaceholder user and rendered the expected name (`Leanne Graham`), email (`Sincere@april.biz`), and company (`Romaguera-Crona`).
- The exhibitor controller made the SOAP request and rendered country names with ISO codes; the service returned 246 countries, including `Afghanistan (AF)`.
- Direct anonymous requests to `/dashboard/member` and `/dashboard/exhibitor` returned HTTP 403. Drupal route-access checks using simulated anonymous, `member`, and `exhibitor` role sessions allowed each role only its matching dashboard.
- The Generic OIDC provider and Auth0 module settings are enabled locally. `/user/login` now returns an Auth0 authorization redirect; `/auth/login` uses the Generic OIDC flow.

**Auth0 account-based validation is still pending.** The browser successfully reaches Auth0 and returns to Drupal's callback URL, so the callback URL configuration is working. Drupal's PHP container cannot currently validate the corporate proxy's certificate when it connects to Auth0's token endpoint; this TLS trust failure prevents the token exchange and callback processing from completing. Add the corporate proxy CA certificate to the PHP container's trusted CA bundle and refresh the trust store, then retry. Do not disable TLS certificate verification. After the token exchange works, verify role mapping, dashboard redirects, and remote logout with the Auth0 test accounts. Do not treat the simulated Drupal role-session checks above as account-based Auth0 validation.

## Authentication module evaluation

| Candidate | Relevant capabilities | Assessment |
| --- | --- | --- |
| [OpenID Connect](https://www.drupal.org/project/openid_connect) | Pluggable OIDC clients, including a Generic client; first-login Drupal account creation; configurable standard-claim mapping; authorization hooks. Release `8.x-1.6` is stable, security-advisory covered, and declares Drupal 11 support. This is the version locked by Composer (`drupal/openid_connect` 1.6.0). | **Selected.** Auth0 speaks OIDC, so the Generic client can use its authorization, token, and UserInfo endpoints without an Auth0-specific dependency. The stable Drupal 11-compatible release is a better fit than adopting an alpha solely for Auth0 branding. |
| [Auth0](https://www.drupal.org/project/auth0) | Auth0-specific Universal Login integration; its 5.x line uses External Auth and requires a cookie secret. | Not selected: the current Drupal 11 release is `5.0.0-alpha1`; Drupal.org lists the project as minimally maintained/maintenance-fixes only and has no supported stable release. Reconsider if a stable maintained release becomes available. |
| [Social Auth](https://www.drupal.org/project/social_auth) | Common framework for external-provider authentication, with a Drupal 11-compatible stable release and provider modules. | Not selected: the project's current provider list does not include Auth0, so an Auth0-specific Social Auth connector would still have to be added or built. That adds integration work without an advantage over the Generic OIDC client here. |

### Selected module: capabilities and limitations

The Generic client implements the OIDC authorization-code login flow, handles the callback, uses `sub` to identify the external account, and provides Drupal's first-login account creation and profile-claim mapping. The login form can display enabled providers. The module's Generic client configuration has fields for the authorization, token, and UserInfo endpoints; it does **not** take an issuer or discovery URL, so endpoint values must be copied from Auth0's discovery metadata.

The module does not automatically translate Auth0 roles into Drupal roles, implement the project's role-specific redirect/access policy, render the REST/SOAP dashboards, or initiate Auth0's RP-initiated logout. Those are custom responsibilities: the `auth_dashboard` OIDC authorization hooks map a namespaced ID-token claim; its routes/controllers enforce and render dashboards; its login callback subscriber selects the dashboard after role mapping; and its confirmation form logs out of Drupal before redirecting to Auth0's `/v2/logout`. Store the client secret outside exported/committed configuration.

The chosen flow uses Auth0 database users, assigned Auth0 roles, and a Post Login Action that adds the role array to a namespaced ID-token claim. It does not depend on Auth0 Organizations. The tenant showed a time-limited trial; check current plan entitlements before relying on any trial-only feature after it expires.

### Sources reviewed

- [OpenID Connect project page and releases](https://www.drupal.org/project/openid_connect)
- [OpenID Connect 8.x-1.6 release notes](https://www.drupal.org/project/openid_connect/releases/8.x-1.6)
- [Auth0 Drupal module project page](https://www.drupal.org/project/auth0)
- [Social Auth project page](https://www.drupal.org/project/social_auth)

