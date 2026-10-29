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

export interface IStaffListOption { id: number; title: string; url: string; listType: string; }

export async function requestLists(listType: string, signal: AbortSignal): Promise<IStaffListOption[]> {
  if (!['listing', 'simple-listing'].includes(listType)) throw new Error('Choose a valid list type.');
  const lists: IStaffListOption[] = [];
  for (let page = 1; page <= 100; page++) {
    const url = new URL('https://staff.eideweb.com/');
    url.search = `?type=sharepoint&ve_staff_lists=1&list_type=${listType}&page=${page}`;
    const response = await fetch(url.toString(), {
      method: 'GET', credentials: 'omit', cache: 'no-store', redirect: 'error', signal,
      headers: { Accept: 'application/json' }
    });
    if (!response.ok) throw new Error(`Unable to load lists (HTTP ${response.status}). Check the site's SharePoint access settings.`);
    if (!(response.headers.get('content-type') || '').includes('application/json')) {
      throw new Error('List discovery is unavailable. Deploy the updated WordPress plugin and exclude SharePoint requests from page caching.');
    }
    const payload = await response.json();
    if (!payload || payload.schemaVersion !== 1 || !Array.isArray(payload.lists) ||
        (payload.nextPage !== null && payload.nextPage !== page + 1)) throw new Error('Invalid list catalog response.');
    for (const item of payload.lists) {
      if (!item || !Number.isInteger(item.id) || item.id < 1 || typeof item.title !== 'string' || item.listType !== listType || typeof item.url !== 'string') {
        throw new Error('Invalid list catalog entry.');
      }
      const safe = listingEndpoint(item.url, true);
      if (!safe.pathname.startsWith(`/${listType}/`)) throw new Error('List type does not match its URL.');
      lists.push({ id: item.id, title: item.title || `List ${item.id}`, url: item.url, listType });
    }
    if (payload.nextPage === null) return lists;
  }
  throw new Error('Too many lists to load. Contact the directory administrator.');
}
