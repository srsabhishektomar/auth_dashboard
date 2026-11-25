/**
 * Auth0 Actions > Library > Build Custom > Login / Post Login.
 *
 * Add this Action to the Login flow and deploy it. Assign the corresponding
 * member or exhibitor role to each user in Auth0 before their login.
 */
exports.onExecutePostLogin = async (event, api) => {
  const namespace = 'https://auth-dashboard.example.com';
  const supportedRoles = new Set(['member', 'exhibitor']);
  const roles = (event.authorization?.roles || [])
    .filter((role) => supportedRoles.has(role));

  api.idToken.setCustomClaim(`${namespace}/roles`, roles);
};
