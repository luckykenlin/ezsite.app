{{--
    The shareable preview document: the page's SAVED state through the real
    layout chain, exactly as publishing would render it. No editor glue — the
    recipient is a stakeholder with a link, not an operator.
--}}
<x-dynamic-component :component="$component" :page="$page" />
