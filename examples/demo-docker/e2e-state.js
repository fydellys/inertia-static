() => {
  const t = (id) => document.querySelector(`[data-testid="${id}"]`)?.textContent?.trim() ?? null
  return {
    url: location.pathname + location.search,
    component: t('component'),
    initial: t('initial-load'),
    originStatic: t('origin-static'),
    originDynamic: t('origin-dynamic'),
    errors: t('inertia-errors'),
    modal1923: document.querySelectorAll('dialog, iframe').length,
    bodyHasPlainJsonError: document.body.innerText.includes('plain JSON response'),
  }
}
