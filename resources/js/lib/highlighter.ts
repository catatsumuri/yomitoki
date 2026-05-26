import type { Highlighter } from 'shiki';

let highlighterPromise: Promise<Highlighter> | null = null;

export function getHighlighter(): Promise<Highlighter> {
    if (!highlighterPromise) {
        highlighterPromise = import('shiki').then(({ createHighlighter }) =>
            createHighlighter({
                themes: ['github-light', 'github-dark'],
                langs: [
                    'typescript',
                    'tsx',
                    'javascript',
                    'jsx',
                    'bash',
                    'json',
                    'php',
                    'yaml',
                    'css',
                    'markdown',
                    'sql',
                    'html',
                ],
            }),
        );
    }

    return highlighterPromise;
}
