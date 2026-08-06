import * as React from 'react';
import * as ReactDom from 'react-dom';
import { Version } from '@microsoft/sp-core-library';
import { IPropertyPaneConfiguration, PropertyPaneTextField } from '@microsoft/sp-property-pane';
import { BaseClientSideWebPart } from '@microsoft/sp-webpart-base';
import EideWebStaffIntegration from './components/EideWebStaffIntegration';
import { IEideWebStaffIntegrationProps } from './components/IEideWebStaffIntegrationProps';

export interface IEideWebStaffIntegrationWebPartProps {
  apiEndpoint: string;
  entraResource: string;
}

export default class EideWebStaffIntegrationWebPart extends BaseClientSideWebPart<IEideWebStaffIntegrationWebPartProps> {
  public render(): void {
    const props: IEideWebStaffIntegrationProps = {
      apiEndpoint: this.properties.apiEndpoint,
      entraResource: this.properties.entraResource,
      displayName: this.context.pageContext.user.displayName,
      email: this.context.pageContext.user.email,
      aadTokenProviderFactory: this.context.aadTokenProviderFactory
    };
    ReactDom.render(React.createElement(EideWebStaffIntegration, props), this.domElement);
  }

  protected onDispose(): void { ReactDom.unmountComponentAtNode(this.domElement); }
  protected get dataVersion(): Version { return Version.parse('1.0'); }

  protected getPropertyPaneConfiguration(): IPropertyPaneConfiguration {
    return { pages: [{ header: { description: 'Staff API connection' }, groups: [{ groupName: 'API', groupFields: [
      PropertyPaneTextField('apiEndpoint', { label: 'API endpoint' }),
      PropertyPaneTextField('entraResource', { label: 'Entra resource URI (optional)' })
    ] }] }] };
  }
}
