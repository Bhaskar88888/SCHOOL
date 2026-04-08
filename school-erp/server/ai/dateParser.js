function parseNaturalDate(text) {
  const now = new Date();
  const lower = text.toLowerCase();

  // Fix: Use word boundaries to prevent false matches
  if (/\btoday\b/.test(lower)) return now;
  if (/\btomorrow\b/.test(lower)) { const d = new Date(now); d.setDate(d.getDate() + 1); return d; }
  if (/\byesterday\b/.test(lower)) { const d = new Date(now); d.setDate(d.getDate() - 1); return d; }
  if (/\bnext\s*monday\b/.test(lower)) { const d = new Date(now); d.setDate(d.getDate() + ((1 - d.getDay() + 7) % 7 || 7)); return d; }
  if (/\blast\s*week\b/.test(lower)) { const d = new Date(now); d.setDate(d.getDate() - 7); return d; }
  if (/\bthis\s*week\b/.test(lower)) { const d = new Date(now); d.setDate(d.getDate() - d.getDay()); return d; }
  if (/\blast\s*month\b/.test(lower)) { const d = new Date(now); d.setMonth(d.getMonth() - 1); return d; }
  if (/\bthis\s*month\b/.test(lower)) { const d = new Date(now); d.setDate(1); return d; }
  const match = text.match(/(\d{1,2})[\/\s-](\d{1,2})[\/\s-](\d{4})/);
  if (match) return new Date(match[3], match[2] - 1, match[1]);
  return null;
}

module.exports = { parseNaturalDate };
