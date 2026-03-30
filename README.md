# Sugar Twig

A Symfony bundle that brings JSX-like component syntax to Twig.

## Example

Define a component (`templates/components/button.html.twig`):

```twig
<button {{ html_attrs({'class': 'bg-primary text-primary-foreground'}) }}>
    {%- block content %}{% endblock -%}
</button>
```

Use it:

```twig
<Button type="submit" className="h-8 px-2.5">Save</Button>
```

It compiles to native Twig `embed`/`include` calls at load time — zero runtime overhead, no custom tags to learn.

Output:

```twig
{% embed 'components/button.html.twig' with {type: 'submit' className: 'h-8 px-2.5'} only %}
    {% block content %}Save{% endblock %}
{% endembed %}
```

Render:

```html
<button type="submit" class="bg-primary text-primary-foreground h-8 px-2.5">Save</button>
```

## Benchmark

The benchmark test at `tests/Preprocessor/ComponentPreprocessorBenchmarkTest.php` covers four tiers:

| Tier    | Time budget | Memory budget | What it tests                                                           |
|---------|-------------|---------------|-------------------------------------------------------------------------|
| no-op   | < 5 µs      | < 1 KB        | Fast-path exit (plain HTML, no components)                              |
| simple  | < 50 µs     | < 8 KB        | 3 flat self-closing components                                          |
| medium  | < 165 µs    | < 32 KB       | Dialog with compound blocks + nested Field/Input                        |
| complex | < 750 µs    | < 64 KB       | Full page: nav, cards, dialog, table (~40 components, 3 nesting levels) |

Each tier runs 1000 iterations for time and 100 for memory, with warmup passes. If a future change introduces a
regression (e.g., O(n²) parsing), the complex tier will blow past 750 µs and fail.

