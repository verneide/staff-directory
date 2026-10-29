import * as React from 'react';
import DOMPurify from 'dompurify';
import styles from './EideWebStaffIntegration.module.scss';
import { IEideWebStaffIntegrationProps, IStaffApiPayload } from './IEideWebStaffIntegrationProps';
import { requestPayload } from './staffApi';
import { bindCardActions } from './cardActions';
import { createDirectoryRuntime } from '../../../runtime/directory';

const runtime = createDirectoryRuntime() as { initialize(root: ShadowRoot): void; dispose(root: ShadowRoot): void };

const EideWebStaffIntegration: React.FC<IEideWebStaffIntegrationProps> = (props) => {
  const [payload, setPayload] = React.useState<IStaffApiPayload>();
  const [error, setError] = React.useState<string>();
  const [reload, setReload] = React.useState(0);
  const shadowHost = React.useRef<HTMLDivElement>(null);

  React.useEffect(() => {
    if (!props.apiEndpoint) { setPayload(undefined); setError(undefined); return; }
    const controller = new AbortController();
    let active = true;
    let timedOut = false;
    setPayload(undefined); setError(undefined);
    const timeout = window.setTimeout(() => { timedOut = true; controller.abort(); }, 30000);
    requestPayload(props.apiEndpoint, props.showTitle, controller.signal).then(result => {
      if (active) setPayload(result);
    }).catch((reason: Error) => {
      if (active) setError(timedOut ? 'The directory request timed out. Please retry.' :
        reason instanceof TypeError ? 'Unable to reach the directory. Check the connection and server CORS configuration.' : reason.message);
    }).then(() => window.clearTimeout(timeout));
    return () => { active = false; window.clearTimeout(timeout); controller.abort(); };
  }, [props.apiEndpoint, props.showTitle, reload]);

  React.useEffect(() => {
    if (!payload || !shadowHost.current) return;
    const root = shadowHost.current.shadowRoot || shadowHost.current.attachShadow({ mode: 'open' });
    const wrapper = document.createElement('div');
    wrapper.className = 've-staff-embed';
    wrapper.innerHTML = DOMPurify.sanitize(payload.html, {
      USE_PROFILES: { html: true }, FORBID_TAGS: ['style', 'link', 'iframe', 'object', 'embed', 'form']
    });
    // CSS is text, never interpolated into HTML; a closing style tag cannot inject markup.
    const style = document.createElement('style');
    style.setAttribute('data-ve-staff', '1');
    style.textContent = payload.css + '\n:host{display:block;width:100%;min-width:0}.ve-staff-embed{width:100%;font:14px/1.5 Arial,sans-serif;color:#222}';
    root.replaceChildren(style, wrapper);
    runtime.initialize(root);
    const disposeCards = bindCardActions(root, props.apiEndpoint);
    return () => { disposeCards(); runtime.dispose(root); root.replaceChildren(); };
  }, [payload]);

  if (!props.apiEndpoint) return <div className={styles.loading}>Edit this web part, choose Staff List or Simple List, then select a list.</div>;
  if (error) return <div className={styles.error} role="alert"><strong>Staff directory unavailable.</strong><span>{error}</span><button type="button" onClick={() => setReload(reload + 1)}>Retry</button></div>;
  if (!payload) return <div className={styles.loading} role="status" aria-live="polite"><span>Loading staff directory…</span><i /><i /><i /></div>;
  return <section className={styles.root} aria-label="Staff directory"><div ref={shadowHost} /></section>;
};

export default EideWebStaffIntegration;
