import { describe, it, expect, vi } from 'vitest'
import { clampText } from './text.js'

// Mock config for tests
vi.stubGlobal('window', { DualChatbotConfig: { maxMessageLength: 10 } })

describe('clampText', () => {
  it('trims whitespace and removes control chars', () => {
    const s = clampText('  hello\x07 ')
    expect(s).toBe('hello')
  })
  it('returns empty for nullish/empty', () => {
    expect(clampText(null)).toBe('')
    expect(clampText('   ')).toBe('')
  })
  it('clamps to configured max', () => {
    const s = clampText('abcdefghijklmnop')
    expect(s.length).toBe(10)
    expect(s).toBe('abcdefghij')
  })
})
