// Entry for bundling. Provides modular utilities and then includes the legacy UI.
import { attachGlobalErrorHandlers } from './logger.js';
import './config.js';
import './text.js';

attachGlobalErrorHandlers();

// Include the legacy monolithic IIFE for now.
// This keeps behavior unchanged while allowing gradual migration.
import '../chatbot.js';

