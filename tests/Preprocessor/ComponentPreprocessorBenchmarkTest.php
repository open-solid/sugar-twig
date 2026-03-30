<?php

declare(strict_types=1);

namespace OpenSolid\SugarTwig\Tests\Preprocessor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use OpenSolid\SugarTwig\Parser\ComponentTagParser;
use OpenSolid\SugarTwig\Preprocessor\ComponentNameResolver;
use OpenSolid\SugarTwig\Preprocessor\ComponentPreprocessor;

final class ComponentPreprocessorBenchmarkTest extends TestCase
{
    private ComponentPreprocessor $preprocessor;

    protected function setUp(): void
    {
        $this->preprocessor = new ComponentPreprocessor(
            new ComponentTagParser(),
            new ComponentNameResolver(),
        );
    }

    // --- Time benchmarks ---

    #[Test]
    #[DataProvider('benchmarkFixtures')]
    public function it_processes_within_time_budget(string $label, string $source, float $maxMicroseconds): void
    {
        // Warmup — let opcache / JIT stabilize
        for ($i = 0; $i < 10; ++$i) {
            $this->preprocessor->process($source);
        }

        $iterations = 1000;
        $start = hrtime(true);

        for ($i = 0; $i < $iterations; ++$i) {
            $this->preprocessor->process($source);
        }

        $elapsedMicroseconds = (hrtime(true) - $start) / 1_000;
        $perIteration = $elapsedMicroseconds / $iterations;

        self::assertLessThan(
            $maxMicroseconds,
            $perIteration,
            \sprintf('[%s] Preprocessing took %.2f µs/iter (budget: %.0f µs)', $label, $perIteration, $maxMicroseconds),
        );
    }

    /**
     * @return iterable<string, array{string, string, float, int}>
     *                          label, source, max µs/iter, max bytes/iter
     */
    public static function benchmarkFixtures(): iterable
    {
        // --- Tier 0: No-op (fast-path exit, no PascalCase tags) ---
        yield 'no-op: plain HTML' => [
            'no-op: plain HTML',
            <<<'TWIG'
            <div class="container mx-auto p-6">
                <header class="flex items-center justify-between mb-8">
                    <h1 class="text-2xl font-bold">Dashboard</h1>
                    <nav class="flex gap-4">
                        <a href="/home" class="text-sm">Home</a>
                        <a href="/settings" class="text-sm">Settings</a>
                    </nav>
                </header>
                <main>
                    {% block content %}{% endblock %}
                </main>
            </div>
            TWIG,
            5.0,    // max µs per iteration
        ];

        // --- Tier 1: Simple (flat self-closing components) ---
        yield 'simple: 3 flat components' => [
            'simple: 3 flat components',
            <<<'TWIG'
            <div class="space-y-4">
                <Alert title="Heads up!" description="You can add components to your app." />
                <Separator />
                <div class="flex gap-2">
                    <Button variant="outline" content="Cancel" />
                    <Button variant="default" content="Submit" />
                </div>
                <Input id="email" name="email" type="email" placeholder="Enter your email" />
            </div>
            TWIG,
            50.0,    // max µs per iteration
        ];

        // --- Tier 2: Medium (nested + compound blocks) ---
        yield 'medium: dialog with blocks' => [
            'medium: dialog with blocks',
            <<<'TWIG'
            <div class="p-8">
                <Card>
                    <div class="flex items-center justify-between p-4">
                        <h2 class="text-lg font-semibold">User Settings</h2>
                        <Dialog title="Edit profile" description="Make changes to your profile here.">
                            <BlockTrigger>
                                <Button variant="outline" content="Edit Profile" />
                            </BlockTrigger>
                            <BlockBody>
                                <div class="grid gap-4 py-4">
                                    <Field id="name" label="Name">
                                        <Input id="name" name="name" placeholder="John Doe" />
                                    </Field>
                                    <Field id="email" label="Email">
                                        <Input id="email" name="email" type="email" placeholder="john@example.com" />
                                    </Field>
                                </div>
                            </BlockBody>
                            <BlockFooter>
                                <Button variant="outline" content="Cancel" />
                                <Button content="Save changes" />
                            </BlockFooter>
                        </Dialog>
                    </div>
                </Card>
            </div>
            TWIG,
            160.0,   // max µs per iteration
        ];

        // --- Tier: Expression + conditional attributes ---
        yield 'expressions: mixed attrs' => [
            'expressions: mixed attrs',
            <<<'TWIG'
            <div class="space-y-4">
                <Alert title="{{ alertTitle }}" description="{{ desc|default('No description') }}" />
                <Button variant="{{ buttonVariant }}" disabled="{{ isDisabled }}" />
                <Badge count="{{ items|length }}" class="px-2 {{ extraClass }}" />
                <Card title="Hello {{ userName }}">
                    <Input {% if required %}required{% endif %} name="email" type="email" />
                    <Button {% if isPrimary %}variant="primary"{% else %}variant="secondary"{% endif %} size="{{ btnSize }}">
                        Submit
                    </Button>
                </Card>
                <Dialog {% if showTitle %}title="{{ dialogTitle }}"{% endif %}>
                    <BlockTrigger>
                        <Button variant="outline" content="Open" />
                    </BlockTrigger>
                    <BlockBody>
                        <Field id="name" label="Name">
                            <Input id="name" {% if hasPlaceholder %}placeholder="{{ placeholder }}"{% endif %} />
                        </Field>
                    </BlockBody>
                </Dialog>
            </div>
            TWIG,
            200.0,   // max µs per iteration
        ];

        // --- Tier 3: Complex (multi-section page, deep nesting, many components) ---
        yield 'complex: full page layout' => [
            'complex: full page layout',
            <<<'TWIG'
            <div class="min-h-screen bg-background">
                {# Navigation #}
                <nav class="border-b">
                    <div class="container flex items-center justify-between h-16 px-4">
                        <Breadcrumb separator="/">
                            <a href="/">Home</a>
                            <a href="/users">Users</a>
                            <span>Profile</span>
                        </Breadcrumb>
                        <div class="flex items-center gap-2">
                            <Tooltip title="Notifications">
                                <Button variant="ghost" size="icon" aria-label="Notifications">
                                    <svg class="size-4"><use href="#bell" /></svg>
                                </Button>
                            </Tooltip>
                            <DropdownMenu>
                                <BlockTrigger>
                                    <Avatar src="/avatar.jpg" alt="User" />
                                </BlockTrigger>
                                <BlockBody>
                                    <DropdownMenuItem content="Profile" />
                                    <DropdownMenuItem content="Settings" />
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem content="Log out" />
                                </BlockBody>
                            </DropdownMenu>
                        </div>
                    </div>
                </nav>

                {# Main content #}
                <main class="container py-8">
                    <div class="grid gap-6 md:grid-cols-2">
                        {# Profile card #}
                        <Card>
                            <div class="p-6">
                                <div class="flex items-center gap-4 mb-4">
                                    <Avatar src="/avatar.jpg" alt="John Doe" size="lg" />
                                    <div>
                                        <h2 class="text-xl font-bold">John Doe</h2>
                                        <Badge variant="secondary" content="Admin" />
                                    </div>
                                </div>
                                <Separator />
                                <div class="mt-4 space-y-2">
                                    <p class="text-sm text-muted-foreground">Member since January 2024</p>
                                </div>
                            </div>
                        </Card>

                        {# Settings card #}
                        <Card>
                            <div class="p-6">
                                <h3 class="text-lg font-semibold mb-4">Preferences</h3>
                                <div class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <Label for="notifications">Email notifications</Label>
                                        <Switch id="notifications" />
                                    </div>
                                    <Separator />
                                    <div class="flex items-center justify-between">
                                        <Label for="theme">Dark mode</Label>
                                        <Switch id="theme" checked />
                                    </div>
                                </div>
                            </div>
                        </Card>
                    </div>

                    {# Edit dialog #}
                    <div class="mt-8">
                        <Dialog title="Edit Profile" description="Update your personal information.">
                            <BlockTrigger>
                                <Button variant="outline" content="Edit Profile" />
                            </BlockTrigger>
                            <BlockBody>
                                <div class="grid gap-4 py-4">
                                    <Field id="fullname" label="Full Name">
                                        <Input id="fullname" name="fullname" />
                                    </Field>
                                    <Field id="bio" label="Bio">
                                        <Textarea id="bio" name="bio" rows="3" />
                                    </Field>
                                    <Field id="role" label="Role">
                                        <NativeSelect id="role" name="role">
                                            <option value="admin">Admin</option>
                                            <option value="user">User</option>
                                        </NativeSelect>
                                    </Field>
                                </div>
                            </BlockBody>
                            <BlockFooter>
                                <Button variant="outline" content="Cancel" />
                                <Button content="Save" />
                            </BlockFooter>
                        </Dialog>
                    </div>

                    {# Data table #}
                    <div class="mt-8">
                        <Card>
                            <div class="p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg font-semibold">Recent Activity</h3>
                                    <div class="flex gap-2">
                                        <Input placeholder="Search..." />
                                        <Button variant="outline" content="Filter" />
                                    </div>
                                </div>
                                <Table>
                                    <TableRow>
                                        <TableHeader content="Action" />
                                        <TableHeader content="Date" />
                                        <TableHeader content="Status" />
                                    </TableRow>
                                    <TableRow>
                                        <TableCell content="Updated profile" />
                                        <TableCell content="2024-01-15" />
                                        <TableCell>
                                            <Badge variant="default" content="Complete" />
                                        </TableCell>
                                    </TableRow>
                                    <TableRow>
                                        <TableCell content="Changed password" />
                                        <TableCell content="2024-01-10" />
                                        <TableCell>
                                            <Badge variant="secondary" content="Pending" />
                                        </TableCell>
                                    </TableRow>
                                </Table>
                            </div>
                        </Card>
                    </div>
                </main>
            </div>
            TWIG,
            750.0,   // max µs per iteration
        ];
    }
}
