# Twig Component

JSX-like syntax for Twig.

## Example

Define a component (`templates/components/button.html.twig`):

```twig
<button class="bg-primary text-primary-foreground h-8 px-2.5" {{ html_attrs() }}>
    {%- block content %}{% endblock -%}
</button>
```

Use it:

```twig
<Button type="submit">Save</Button>
```

Output:

```twig
{% embed 'components/button.html.twig' with {type: 'submit'} only %}
    {% block content %}Save{% endblock %}
{% endembed %}
```

## Benchmark

The benchmark test at tests/Preprocessor/ComponentPreprocessorBenchmarkTest.php covers 4 tiers:

┌─────────┬────────────┬──────────────┬────────────────────────────────────────────────────────────────────┐
│  Tier   │   Time     │   Memory     │                           What it tests                            │
│         │   budget   │    budget    │                                                                    │
├─────────┼────────────┼──────────────┼────────────────────────────────────────────────────────────────────┤
│ no-op   │ < 5 µs     │ < 1 KB       │ Fast-path exit (plain HTML, no components)                         │
├─────────┼────────────┼──────────────┼────────────────────────────────────────────────────────────────────┤
│ simple  │ < 50 µs    │ < 8 KB       │ 3 flat self-closing components                                     │
├─────────┼────────────┼──────────────┼────────────────────────────────────────────────────────────────────┤
│ medium  │ < 160 µs   │ < 32 KB      │ Dialog with compound blocks + nested Field/Input                   │
├─────────┼────────────┼──────────────┼────────────────────────────────────────────────────────────────────┤
│ complex │ < 750 µs   │ < 64 KB      │ Full page: nav, cards, dialog, table (~40 components, 3 nesting    │
│         │            │              │ levels)                                                            │
└─────────┴────────────┴──────────────┴────────────────────────────────────────────────────────────────────┘

Each tier runs 1000 iterations for time and 100 for memory, with warmup passes. If a future change introduces a
regression (e.g., O(n²) parsing), the complex tier will blow past 750 µs and fail.

