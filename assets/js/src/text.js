import { Config } from './config.js';

// Normalize and clamp user-provided message text
export function clampText(input) {
  let s = (input == null) ? '' : String(input);
  s = s.replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '');
  s = s.trim();
  if (!s) return '';
  const max = Number(Config.maxMessageLength || 2000);
  if (s.length > max) s = s.slice(0, max);
  return s;
}

