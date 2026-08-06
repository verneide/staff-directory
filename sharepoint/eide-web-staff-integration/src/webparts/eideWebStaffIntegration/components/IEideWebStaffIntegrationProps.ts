import { AadTokenProviderFactory } from '@microsoft/sp-http';

export interface IEideWebStaffIntegrationProps {
  apiEndpoint: string;
  entraResource: string;
  displayName: string;
  email: string;
  aadTokenProviderFactory: AadTokenProviderFactory;
}

export interface IStaffApiPayload {
  html?: string;
  css?: string;
  data?: ReadonlyArray<Readonly<Record<string, unknown>>>;
}
