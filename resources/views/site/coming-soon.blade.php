{{--
    What a tenant domain serves while nothing on it has been published.

    The state it replaces: a bare 404, on a link the owner may well have already
    sent to customers, on the day they signed up. `ProvisionSiteFromTemplate`
    leaves every page in Draft for review, so this is the FIRST thing a
    brand-new site says — which makes it worth saying properly.

    `noindex` rather than a 404 or a 503 status: the site answers, so the owner
    testing their own link (and any uptime check) sees success, while a crawler
    is told not to keep the placeholder. See bootstrap/app.php for where the
    choice is made.
--}}
@use('App\Site\BindResolver')

<x-site.notice
    :business="resolve(BindResolver::class)->business()"
    title="Coming soon"
    heading="Coming soon"
    body="This site is being built right now. Please check back shortly."
    :noindex="true"
/>
