export default [
  {
    files: ['assets/js/**/*.js'],
    ignores: ['assets/js/**/*.min.js'],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'module',
      globals: {
        window: "readonly",
        document: "readonly",
        console: "readonly",
        fetch: "readonly",
        location: "readonly",
        getComputedStyle: "readonly",
        setTimeout: "readonly",
        clearTimeout: "readonly",
        setInterval: "readonly",
        clearInterval: "readonly",
        requestAnimationFrame: "readonly",
        navigator: "readonly",
        alert: "readonly",
        Event: "readonly",
        Node: "readonly",
        CSS: "readonly",
        performance: "readonly",
        sessionStorage: "readonly",
        MutationObserver: "readonly",
        MediaRecorder: "readonly",
        Blob: "readonly",
        FormData: "readonly",
        AbortController: "readonly",
        TextDecoder: "readonly",
        visualViewport: "readonly",
        crypto: "readonly"
        ,
        jQuery: "readonly",
        DualChatbotConfig: "readonly",
        appendMessageToContainer: "readonly",
        normalizeActionBars: "readonly",
        buildMsgActions: "readonly",
        pruneEmptyMessages: "readonly",
        uiStart: "readonly",
        confirm: "readonly"
      }
    },
    rules: {
      'no-unused-vars': ['warn', { 
        'argsIgnorePattern': '^_|^e$|^err$|^mutations$|^uiStart$',
        'varsIgnorePattern': '^_|^uiStart$',
        'caughtErrorsIgnorePattern': '^_|^e$|^err$'
      }],
      'no-undef': 'error',
    },
  },
];
