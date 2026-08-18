export const systemConfig = {
  system: "oobDISCOVERY",
  discoveryType: "clinician",
  version: "0.6.0",
  storageKey: "oobdiscovery-clinical-audience-map-v5",
  submissionEndpoint: "https://api.oobcreative.com/discovery/submit/",
  allowedProductionOrigin: "https://discovery.oobcreative.com",
  allowJsonBackup: true,
  estimatedMinutes: "About 30 minutes; 45 with a new audience",
  submissionTimeoutMs: 25000,
  draftRetentionDays: 14,
  dictationInitialTimeoutMs: 6000,
  dictationSilenceTimeoutMs: 1800,
  dictationNoticeKey: "oobdiscovery-dictation-notice-v1"
};
