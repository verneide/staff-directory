import { IStaffApiPayload } from './IEideWebStaffIntegrationProps';

export function listingEndpoint(value: string, showTitle: boolean): URL {
  let url: URL;
  try { url = new URL(value); } catch (_) {
    throw new Error('Configure this web part with a Staff List or Simple List URL from https://staff.eideweb.com.');
  }
  if (url.origin !== 'https://staff.eideweb.com' || url.username || url.password ||
      !/^\/(listing|simple-listing)\/[^/]+\/?$/.test(url.pathname)) {
    throw new Error('Enter a listing or simple-listing URL from https://staff.eideweb.com.');
  }
  // Preserve listing options (including legacy embed access parameters).
  url.searchParams.delete('embed_version');
  url.searchParams.delete('debug');
  url.searchParams.set('type', 'sharepoint');
  url.searchParams.set('title', showTitle ? '1' : '0');
  url.hash = '';
  return url;
}

export async function requestPayload(endpoint: string, showTitle: boolean, signal: AbortSignal): Promise<IStaffApiPayload> {
  const url = listingEndpoint(endpoint, showTitle);
  for (let attempt = 0; attempt < 3; attempt++) {
    const response = await fetch(url.toString(), {
      method: 'GET', credentials: 'omit', cache: 'no-store', redirect: 'error', signal,
      headers: { Accept: 'application/json' }
    });
    if ((response.status === 429 || response.status >= 500) && attempt < 2) {
      await new Promise<void>(resolve => window.setTimeout(resolve, 500 * (attempt + 1)));
      if (signal.aborted) throw new Error('Request cancelled.');
      continue;
    }
    if (!response.ok) throw new Error(`Directory returned HTTP ${response.status}. Check the list access settings and SharePoint origin configuration.`);
    if (!(response.headers.get('content-type') || '').includes('application/json')) {
      throw new Error('The site did not return JSON. Deploy the SharePoint API changes and exclude this endpoint from page caching.');
    }
    const payload = await response.json() as IStaffApiPayload;
    if (!payload || payload.schemaVersion !== 1 || typeof payload.html !== 'string' || typeof payload.css !== 'string' ||
        !['listing', 'simple-listing'].includes(payload.listType) || !Number.isInteger(payload.listId)) {
      throw new Error('The directory returned an unsupported response format.');
    }
    return payload;
  }
  throw new Error('The directory is temporarily unavailable.');
}
