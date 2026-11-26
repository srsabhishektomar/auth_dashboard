# AI agent log

## Milestone 1: Requirements and module structure

- Prompt: review `task.md` and create a custom Drupal 11 module initial boilerplate.
- Agent contribution: identified routes, roles, controllers, API integration service, configuration, and deliverable documentation.
- Human review: selected DDEV as the runtime and OpenID Connect as the contributed authentication module.
- Validation: DDEV site is running and both custom and contributed modules are enabled.

## Milestone 2: Dependency and setup

- Agent contribution: documented OpenID Connect/ Auth0 setup and DDEV Composer/Drush commands.
- Human review: credentials are intentionally placeholders; no secrets are stored in source.
- Validation: Composer manifest validates; Auth0 client settings remain local setup steps.

## Milestone 3: Auth0 tenant setup

- Agent contribution: created the `Drupal 11 Auth Dashboard` Regular Web Application and the `member` and `exhibitor` Auth0 roles in the signed-in development tenant.
- Human review: selected the `member@test.com` and `exhibitor@test.com` test addresses. Passwords must be entered directly in Auth0, not shared in prompts or committed.
- Validation: the dashboard user list remained empty after the account-creation form was opened; user creation and role assignment are still pending. The app callback/logout/origin settings and Login Action still need to be saved and deployed.

## Milestone 4: Login, role mapping, and logout

- Agent contribution: added `/auth/login`, the CSRF-protected `/auth/logout` confirmation form, Auth0 logout redirect, strict single-role claim mapping, and a response subscriber that redirects after OpenID Connect finishes role mapping.
- Human review: dashboard roles are installed from module config; the Auth0 client secret remains unconfigured and uncommitted.
- Validation: DDEV cache rebuild and route generation succeeded; both Drupal roles exist; member/exhibitor claims allow login while missing or multiple dashboard roles are denied; the logout form builds; anonymous logout is denied. A temporary local OIDC placeholder produced the expected 302 authorization redirect and `/v2/logout` URL, then was restored. End-to-end Auth0 login/logout remains pending real provider credentials and the deployed Auth0 Action.

## Milestone 5: Dashboard, access, and API validation

- Agent contribution: verified both dashboard controllers against the configured public services.
- Human review: distinguished route checks using simulated Drupal roles from real Auth0 account tests.
- Validation: JSONPlaceholder returned the expected member name, email, and company. The SOAP endpoint returned 246 countries and the exhibitor controller rendered country names with ISO codes. Anonymous HTTP requests to both dashboard paths returned 403; route access checks allowed only each simulated role's matching dashboard. The Generic OIDC provider remains disabled and the Auth0 test accounts are not present, so login, logout, callback mapping, and post-login redirects remain unverified with real accounts.

## Milestone 6: Logout configuration error

- Agent contribution: traced the `/user/logout` exception to the unselected Auth0 5.0.0-alpha1 module, which overrides Drupal's core logout route and requires its own separate configuration.
- Human review: retained the selected OpenID Connect Generic integration and custom `/auth/logout` flow; the Auth0-specific module has no enabled dependents and is not required by the custom module.
- Validation: uninstalled the unconfigured `auth0` module, rebuilt caches, and verified `/user/logout` now resolves to Drupal's core `UserController::logout`. The Generic OpenID Connect and Auth Dashboard modules remain enabled.

## Milestone 7: Authentication module dependencies

- Agent contribution: declared `openid_connect` and `auth0` as `auth_dashboard` dependencies and invoked Drupal's module installer to enable both in the existing DDEV site.
- Human review: the Generic OpenID Connect client remains the active login/role-mapping implementation; the Auth0-specific module is enabled per request but is not used by that flow.
- Validation: confirmed all three modules are enabled and `auth0.logout` owns `/user/logout`. Following the user's choice to keep both, adjusted the Auth0 form validator for regional tenant domains and updated PHP 8.4 nullable signatures. Copied the existing OIDC client ID/secret/domain into local Auth0 active configuration and generated a cookie secret without printing either secret. The Auth0 client service instantiates, and `/user/login` returns an Auth0 authorization redirect.
