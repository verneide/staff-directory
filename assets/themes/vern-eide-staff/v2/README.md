# Staff rendering v2

V2 embeds use the plugin's `public/v2/embed-loader.js`. The loader immediately paints a responsive skeleton, requests the existing listing URL with `embed_version=2`, and mounts the resulting staff container and its inline, embed-specific stylesheet in a Shadow DOM. The v2 listing response intentionally omits the normal WordPress header, footer, and their unrelated assets. This retains the existing templates and card styling without duplicating query/rendering logic or requiring cross-origin CSS requests. Existing `?type=script` embeds remain unchanged.

Allow an embedding origin with the `ve_staff_v2_allowed_origins` filter. Do not use `*` for authenticated content.
