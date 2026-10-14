/** Standard amortising-loan payment. Pure function, easy to unit test. */
export function calculatePayment({ price, downPct, years, ratePct }) {
  const down = price * (downPct / 100);
  const loan = price - down;
  const n = years * 12;
  const r = ratePct / 100 / 12;
  const monthly = r === 0 ? loan / n : (loan * r) / (1 - (1 + r) ** -n);
  const total = monthly * n;
  return { down, loan, monthly, total, interest: total - loan };
}
