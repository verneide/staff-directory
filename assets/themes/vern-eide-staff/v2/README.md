# Staff rendering v2

V2 embeds use the plugin's `public/v2/embed-loader.js`. The loader immediately paints a responsive skeleton, requests the existing listing URL with `embed_version=2`, and mounts the resulting staff container in a Shadow DOM. This retains the existing templates and card styling without duplicating query/rendering logic. Existing `?type=script` embeds remain unchanged.

Allow an embedding origin with the `ve_staff_v2_allowed_origins` filter. Do not use `*` for authenticated content.
