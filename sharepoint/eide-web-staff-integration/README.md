# Eide Web Staff Integration (SPFx 1.18.2)

The web part sends the SharePoint user's email and display name to the configured API, optionally obtains an Entra bearer token, disables caching, retries transient request failures, sanitizes returned HTML, and renders it in a Shadow DOM so external styles cannot affect SharePoint.

## Entra API permission

1. Expose a delegated scope such as `Staff.Read` on the external API app registration and set its Application ID URI to the value used by `entraResource`.
2. Replace `Eide Staff API` and `Staff.Read` in `config/package-solution.json` with the exact Entra enterprise application display name and delegated scope. Remove `webApiPermissionRequests` when the API is anonymous.
3. Package and deploy the `.sppkg` to the tenant app catalog.
4. In SharePoint Admin Center, open **Advanced > API access** and approve the pending request. A tenant administrator must consent before `AadTokenProvider` can issue the token.
5. Configure the API CORS policy to allow the exact SharePoint tenant origin and validate the token audience, issuer, signature, expiry, and delegated scope server-side.

Never put client secrets in a web part. SPFx uses the signed-in user's delegated identity.
