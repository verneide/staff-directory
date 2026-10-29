export interface IEideWebStaffIntegrationProps {
  apiEndpoint: string;
  showTitle: boolean;
}

export interface IStaffApiPayload {
  schemaVersion: 1;
  listId: number;
  listType: 'listing' | 'simple-listing';
  html: string;
  css: string;
}
