import * as React from 'react';
import * as ReactDom from 'react-dom';
import { Version } from '@microsoft/sp-core-library';
import { IPropertyPaneConfiguration, PropertyPaneTextField, PropertyPaneToggle, PropertyPaneDropdown, PropertyPaneButton, PropertyPaneLabel } from '@microsoft/sp-property-pane';
import { BaseClientSideWebPart } from '@microsoft/sp-webpart-base';
import EideWebStaffIntegration from './components/EideWebStaffIntegration';
import { IEideWebStaffIntegrationProps } from './components/IEideWebStaffIntegrationProps';
import { IStaffListOption, requestLists } from './components/staffApi';

export interface IEideWebStaffIntegrationWebPartProps {
  apiEndpoint: string;
  showTitle: boolean;
  listType?: string;
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

  public render(): void {
    const props: IEideWebStaffIntegrationProps = {
      apiEndpoint: this.properties.apiEndpoint,
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
      this.catalogMessage = request.signal.aborted ? 'Loading timed out. Select Refresh lists to try again.' : (error instanceof Error ? error.message : 'Unable to load lists. Select Refresh lists to retry.');
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
    return { pages: [{ header: { description: 'Connect to staff.eideweb.com using approved SharePoint access. No password is required.' }, groups: [
      { groupName: 'Directory', groupFields: [
        PropertyPaneDropdown('listType', { label: 'List type', selectedKey: this.listType, options: [
          { key: 'listing', text: 'Staff List' }, { key: 'simple-listing', text: 'Simple List' }
        ] }),
        PropertyPaneLabel('catalogStatus', { text: this.catalogMessage }),
        PropertyPaneDropdown('apiEndpoint', { label: 'List', options, selectedKey: saved, disabled: this.loading || !options.length }),
        PropertyPaneButton('refreshLists', { text: 'Refresh lists', disabled: this.loading, onClick: () => { void this.loadLists(); } }),
        PropertyPaneToggle('showTitle', { label: 'Show list title' })
      ] },
      { groupName: 'Advanced', isCollapsed: true, groupFields: [
        PropertyPaneTextField('apiEndpoint', { label: 'Manual list URL', description: 'Optional: retain an existing embed URL and its parameters. Do not enter WordPress passwords here.' })
      ] }
    ] }] };
  }
}
