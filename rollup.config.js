import resolve from '@rollup/plugin-node-resolve';
import commonjs from '@rollup/plugin-commonjs';
import terser from '@rollup/plugin-terser';
import replace from '@rollup/plugin-replace';

// Use the modular entry which reuses the legacy IIFE to preserve behavior
const input = 'assets/js/src/index.js';

export default {
  input,
  output: {
    file: 'assets/js/chatbot.min.js',
    format: 'iife',
    sourcemap: true, // Source maps für besseres debugging
  },
  plugins: [
    replace({
      preventAssignment: true,
      'process.env.NODE_ENV': JSON.stringify('production'),
    }),
    resolve(),
    commonjs(),
    terser({ 
      compress: {
        drop_console: true,    // Entfernt console.* in production
        drop_debugger: true,   // Entfernt debugger statements
        passes: 2,             // Mehrfache Optimierung
        pure_funcs: ['console.log', 'console.debug', 'console.info', 'console.warn']
      }, 
      mangle: true,
      format: {
        comments: false,       // Entfernt Kommentare
        ascii_only: false,     // Behält UTF-8 Zeichen
        beautify: false
      }
    }),
  ],
};
