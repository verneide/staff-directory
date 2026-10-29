import * as React from 'react';
import * as ReactDom from 'react-dom';
import { Version } from '@microsoft/sp-core-library';
import { IPropertyPaneConfiguration, PropertyPaneTextField, PropertyPaneToggle, PropertyPaneDropdown, PropertyPaneButton, PropertyPaneLabel } from '@microsoft/sp-property-pane';
import { BaseClientSideWebPart } from '@microsoft/sp-webpart-base';
import EideWebStaffIntegration from './components/EideWebStaffIntegration';
import { IEideWebStaffIntegrationProps } from './components/IEideWebStaffIntegrationProps';
import { IStaffListOption, requestLists, withEmbedApiKey, catalogFailureMessage } from './components/staffApi';

export interface IEideWebStaffIntegrationWebPartProps {
  apiEndpoint: string;
  showTitle: boolean;
  listType?: string;
  embedApiKey?: string;
}

export default class EideWebStaffIntegrationWebPart extends BaseClientSideWebPart<IEideWebStaffIntegrationWebPartProps> {
  private lists: IStaffListOption[] = [];
  private catalogRequest?: AbortController;
  private loading: boolean = false;
  private catalogMessage: string = '';
  private disposed: boolean = false;

  private get listType(): string {
    return this.properties.listType || (this.properties.apiEndpoint?.includes('/simple-listing/') ? 'simple-listing' : 'listing');
  }

  protected onInit(): Promise<void> {
    // Retain the internal key from a previously saved embed URL when switching lists.
    if (this.properties.embedApiKey === undefined && this.properties.apiEndpoint) {
      try { this.properties.embedApiKey = new URL(this.properties.apiEndpoint).searchParams.get('api') || ''; } catch (_) { /* Existing URL validation handles malformed values. */ }
    }
    return super.onInit();
  }

  public render(): void {
    let endpoint = this.properties.apiEndpoint;
    try { endpoint = withEmbedApiKey(endpoint, this.properties.embedApiKey); } catch (_) { /* Render the existing URL-validation message. */ }
    const props: IEideWebStaffIntegrationProps = {
      apiEndpoint: endpoint,
      showTitle: this.properties.showTitle !== false
    };
    ReactDom.render(React.createElement(EideWebStaffIntegration, props), this.domElement);
  }

  protected onDispose(): void {
    this.disposed = true;
    this.catalogRequest?.abort();
    ReactDom.unmountComponentAtNode(this.domElement);
  }
  protected get dataVersion(): Version { return Version.parse('1.0'); }
  protected onPropertyPaneConfigurationStart(): void { void this.loadLists(); }

  private async loadLists(): Promise<void> {
    this.catalogRequest?.abort();
    const request = new AbortController();
    this.catalogRequest = request;
    this.loading = true;
    this.catalogMessage = 'Loading lists…';
    this.context.propertyPane.refresh();
    const timeout = window.setTimeout(() => request.abort(), 30000);
    try {
      const lists = await requestLists(this.listType, request.signal);
      if (this.disposed || this.catalogRequest !== request) return;
      this.lists = lists;
      this.catalogMessage = lists.length ? 'Choose a list below. Refresh to see changes made in WordPress.' : 'No published lists of this type are available.';
    } catch (error) {
      if (this.disposed || this.catalogRequest !== request) return;
      this.catalogMessage = request.signal.aborted ? 'Loading timed out. Select Refresh lists to try again.' : catalogFailureMessage(error);
    } finally {
      window.clearTimeout(timeout);
      if (!this.disposed && this.catalogRequest === request) {
        this.loading = false;
        this.context.propertyPane.refresh();
      }
    }
  }

  protected onPropertyPaneFieldChanged(propertyPath: string, oldValue: unknown, newValue: unknown): void {
    super.onPropertyPaneFieldChanged(propertyPath, oldValue, newValue);
    if (propertyPath === 'listType') {
      this.properties.apiEndpoint = '';
      this.lists = [];
      this.render();
      void this.loadLists();
    } else if (propertyPath === 'embedApiKey') {
      this.render();
    } else if (propertyPath === 'apiEndpoint') {
      const type = this.properties.apiEndpoint?.includes('/simple-listing/') ? 'simple-listing' : 'listing';
      const previousType = this.properties.listType || (typeof oldValue === 'string' && oldValue.includes('/simple-listing/') ? 'simple-listing' : 'listing');
      const changedType = type !== previousType;
      this.properties.listType = type;
      this.render();
      if (changedType) { this.lists = []; void this.loadLists(); }
      this.context.propertyPane.refresh();
    }
  }

  protected getPropertyPaneConfiguration(): IPropertyPaneConfiguration {
    const options = this.lists.map(list => ({ key: list.url, text: `${list.title} (#${list.id})` }));
    const saved = this.properties.apiEndpoint;
    if (saved && !options.some(option => option.key === saved)) {
      options.unshift({ key: saved, text: 'Current saved list (custom URL or not in catalog)' });
    }
    return { pages: [{ header: { description: 'Display the directory natively in SharePoint. No iframe, script tag or WordPress password is needed.' }, groups: [
      { groupName: 'Setup', groupFields: [
        PropertyPaneLabel('setupSteps', { text: '1. Deploy the SharePoint plugin and theme updates to staff.eideweb.com. 2. For an internal list, copy only the api parameter value from its existing embed URL into the field below. 3. Choose the list type and list, then publish your SharePoint page.' }),
        PropertyPaneTextField('embedApiKey', { label: 'Internal-list embed API key (optional)', description: 'The value after api= in your existing embed URL. Kept when switching lists. This browser-visible embed key is not a WordPress application password. Leave blank for public lists.' }),
        PropertyPaneLabel('internalHelp', { text: 'Internal content also requires the list’s Internal Page setting in WordPress. The embed key does not fix connection or CORS errors. Styling and list settings come from WordPress on reload; directory interactions run inside this web part.' })
      ] },
      { groupName: 'Directory', groupFields: [
        PropertyPaneDropdown('listType', { label: 'List type', selectedKey: this.listType, options: [
          { key: 'listing', text: 'Staff List' }, { key: 'simple-listing', text: 'Simple List' }
        ] }),
        PropertyPaneLabel('catalogStatus', { text: this.catalogMessage }),
        PropertyPaneDropdown('apiEndpoint', { label: 'List', options, selectedKey: saved, disabled: this.loading || !options.length }),
        PropertyPaneButton('refreshLists', { text: 'Refresh lists', disabled: this.loading, onClick: () => { void this.loadLists(); } }),
        PropertyPaneToggle('showTitle', { label: 'Show list title' })
      ] },
      { groupName: 'Connection help', isCollapsed: true, groupFields: [
        PropertyPaneLabel('connectionHelp', { text: 'The site must allow https://verneide.sharepoint.com and return JSON with the matching Access-Control-Allow-Origin header. Exclude requests with type=sharepoint from hosting/CDN page caching. If loading fails, open Developer Tools → Console and look for the staff.eideweb.com request. Check redirects, CORS and connect-src policy. Refresh lists after fixing the connection.' })
      ] },
      { groupName: 'Advanced', isCollapsed: true, groupFields: [
        PropertyPaneTextField('apiEndpoint', { label: 'Manual list URL', description: 'Optional: retain an existing embed URL and its parameters. Do not enter WordPress passwords here.' })
      ] }
    ] }] };
  }
}
