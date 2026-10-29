import * as React from 'react';
import * as ReactDom from 'react-dom';
import { Version } from '@microsoft/sp-core-library';
import { IPropertyPaneConfiguration, PropertyPaneTextField, PropertyPaneToggle } from '@microsoft/sp-property-pane';
import { BaseClientSideWebPart } from '@microsoft/sp-webpart-base';
import EideWebStaffIntegration from './components/EideWebStaffIntegration';
import { IEideWebStaffIntegrationProps } from './components/IEideWebStaffIntegrationProps';

export interface IEideWebStaffIntegrationWebPartProps {
  apiEndpoint: string;
  showTitle: boolean;
}

export default class EideWebStaffIntegrationWebPart extends BaseClientSideWebPart<IEideWebStaffIntegrationWebPartProps> {
  public render(): void {
    const props: IEideWebStaffIntegrationProps = {
      apiEndpoint: this.properties.apiEndpoint,
      showTitle: this.properties.showTitle !== false
    };
    ReactDom.render(React.createElement(EideWebStaffIntegration, props), this.domElement);
  }

  protected onDispose(): void { ReactDom.unmountComponentAtNode(this.domElement); }
  protected get dataVersion(): Version { return Version.parse('1.0'); }

  protected getPropertyPaneConfiguration(): IPropertyPaneConfiguration {
    return { pages: [{ header: { description: 'Staff API connection' }, groups: [{ groupName: 'API', groupFields: [
      PropertyPaneTextField('apiEndpoint', { label: 'Staff List or Simple List URL', description: 'Paste the existing staff.eideweb.com listing URL, including any required embed parameters.' }),
      PropertyPaneToggle('showTitle', { label: 'Show list title' })
    ] }] }] };
  }
}
