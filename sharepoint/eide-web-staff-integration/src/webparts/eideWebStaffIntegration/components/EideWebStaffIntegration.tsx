import * as React from 'react';
import DOMPurify from 'dompurify';
import { AadTokenProvider } from '@microsoft/sp-http';
import styles from './EideWebStaffIntegration.module.scss';
import { IEideWebStaffIntegrationProps, IStaffApiPayload } from './IEideWebStaffIntegrationProps';

const RETRY_DELAYS: ReadonlyArray<number> = [250, 750, 1500];
const delay = (milliseconds: number): Promise<void> => new Promise((resolve) => window.setTimeout(resolve, milliseconds));

const getToken = async (props: IEideWebStaffIntegrationProps): Promise<string | undefined> => {
  if (!props.entraResource.trim()) return undefined;
  const provider: AadTokenProvider = await props.aadTokenProviderFactory.getTokenProvider();
  return provider.getToken(props.entraResource.trim());
};

const requestPayload = async (props: IEideWebStaffIntegrationProps, signal: AbortSignal): Promise<IStaffApiPayload> => {
  const token = await getToken(props);
  const endpoint = new URL(props.apiEndpoint);
  endpoint.searchParams.set('t', Date.now().toString());
  let lastError: Error | undefined;
  for (let attempt = 0; attempt <= RETRY_DELAYS.length; attempt += 1) {
    try {
      const response = await fetch(endpoint.toString(), {
        method: 'POST', cache: 'no-store', signal,
        headers: { 'Accept': 'application/json, text/html', 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
        body: JSON.stringify({ email: props.email, displayName: props.displayName })
      });
      const body = await response.text();
      if (!response.ok) throw new Error(`Staff API returned HTTP ${response.status}: ${body.slice(0, 500)}`);
      return response.headers.get('content-type')?.includes('application/json') ? JSON.parse(body) as IStaffApiPayload : { html: body };
    } catch (error) {
      if (signal.aborted) throw error;
      lastError = error instanceof Error ? error : new Error(String(error));
      if (attempt < RETRY_DELAYS.length) {
        console.warn('Staff API request retry', { endpoint: endpoint.origin, attempt: attempt + 1, error: lastError.message });
        await delay(RETRY_DELAYS[attempt]);
      }
    }
  }
  throw lastError || new Error('Staff API request failed without an error response.');
};

const EideWebStaffIntegration: React.FC<IEideWebStaffIntegrationProps> = (props) => {
  const [payload, setPayload] = React.useState<IStaffApiPayload | undefined>();
  const [error, setError] = React.useState<string | undefined>();
  const shadowHost = React.useRef<HTMLDivElement>(null);

  React.useEffect(() => {
    const controller = new AbortController();
    setPayload(undefined); setError(undefined);
    requestPayload(props, controller.signal).then(setPayload).catch((reason: Error) => {
      if (!controller.signal.aborted) setError(reason.message);
    });
    return () => controller.abort();
  }, [props.apiEndpoint, props.entraResource, props.email, props.displayName]);

  React.useEffect(() => {
    if (!payload || !shadowHost.current) return;
    const root = shadowHost.current.shadowRoot || shadowHost.current.attachShadow({ mode: 'open' });
    const html = payload.html || (payload.data ? `<pre>${DOMPurify.sanitize(JSON.stringify(payload.data, null, 2))}</pre>` : '');
    root.innerHTML = `<style>:host{display:block}*,*:before,*:after{box-sizing:border-box}${payload.css || ''}</style>${DOMPurify.sanitize(html, { USE_PROFILES: { html: true } })}`;
  }, [payload]);

  if (error) return <div className={styles.error} role="alert"><strong>Staff directory unavailable.</strong><span>{error}</span></div>;
  if (!payload) return <div className={styles.loading} role="status" aria-live="polite"><span>Loading staff directory…</span><i /><i /><i /></div>;
  return <section className={styles.root} aria-label={`Staff directory for ${props.displayName}`}><div ref={shadowHost} /></section>;
};

export default EideWebStaffIntegration;
