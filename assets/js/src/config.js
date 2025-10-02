// Centralized access to localized WP config
export const Config = {
  get raw() {
    return (typeof window !== 'undefined' && window.DualChatbotConfig) ? window.DualChatbotConfig : {};
  },
  get restUrl() {
    return String(this.raw.restUrl || '');
  },
  get analyticsRestUrl() {
    return String(this.raw.analyticsRestUrl || '');
  },
  get logRestUrl() {
    return String(this.raw.logRestUrl || '');
  },
  get nonce() {
    return String(this.raw.nonce || '');
  },
  get debugEnabled() {
    return !!this.raw.debugEnabled;
  },
  get maxMessageLength() {
    return Number(this.raw.maxMessageLength || 2000);
  }
};

