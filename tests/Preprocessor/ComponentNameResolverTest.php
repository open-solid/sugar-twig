<?php

declare(strict_types=1);

namespace OpenSolid\SugarTwig\Tests\Preprocessor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use OpenSolid\SugarTwig\Preprocessor\ComponentNameResolver;

final class ComponentNameResolverTest extends TestCase
{
    private ComponentNameResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ComponentNameResolver();
    }

    #[Test]
    #[DataProvider('componentNames')]
    public function it_resolves_pascal_case_to_template_path(string $name, string $expected): void
    {
        self::assertSame($expected, $this->resolver->resolve($name));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function componentNames(): iterable
    {
        // Single word
        yield 'Alert' => ['Alert', 'components/alert.html.twig'];
        yield 'Avatar' => ['Avatar', 'components/avatar.html.twig'];
        yield 'Badge' => ['Badge', 'components/badge.html.twig'];
        yield 'Button' => ['Button', 'components/button.html.twig'];
        yield 'Card' => ['Card', 'components/card.html.twig'];
        yield 'Checkbox' => ['Checkbox', 'components/checkbox.html.twig'];
        yield 'Dialog' => ['Dialog', 'components/dialog.html.twig'];
        yield 'Drawer' => ['Drawer', 'components/drawer.html.twig'];
        yield 'Empty' => ['Empty', 'components/empty.html.twig'];
        yield 'Field' => ['Field', 'components/field.html.twig'];
        yield 'Input' => ['Input', 'components/input.html.twig'];
        yield 'Label' => ['Label', 'components/label.html.twig'];
        yield 'Menubar' => ['Menubar', 'components/menubar.html.twig'];
        yield 'Pagination' => ['Pagination', 'components/pagination.html.twig'];
        yield 'Popover' => ['Popover', 'components/popover.html.twig'];
        yield 'Progress' => ['Progress', 'components/progress.html.twig'];
        yield 'Separator' => ['Separator', 'components/separator.html.twig'];
        yield 'Sheet' => ['Sheet', 'components/sheet.html.twig'];
        yield 'Skeleton' => ['Skeleton', 'components/skeleton.html.twig'];
        yield 'Slider' => ['Slider', 'components/slider.html.twig'];
        yield 'Spinner' => ['Spinner', 'components/spinner.html.twig'];
        yield 'Switch' => ['Switch', 'components/switch.html.twig'];
        yield 'Table' => ['Table', 'components/table.html.twig'];
        yield 'Tabs' => ['Tabs', 'components/tabs.html.twig'];
        yield 'Textarea' => ['Textarea', 'components/textarea.html.twig'];
        yield 'Toggle' => ['Toggle', 'components/toggle.html.twig'];
        yield 'Tooltip' => ['Tooltip', 'components/tooltip.html.twig'];
        yield 'Typography' => ['Typography', 'components/typography.html.twig'];
        yield 'Kbd' => ['Kbd', 'components/kbd.html.twig'];
        yield 'Sidebar' => ['Sidebar', 'components/sidebar.html.twig'];
        yield 'Accordion' => ['Accordion', 'components/accordion.html.twig'];
        yield 'Breadcrumb' => ['Breadcrumb', 'components/breadcrumb.html.twig'];
        yield 'Collapsible' => ['Collapsible', 'components/collapsible.html.twig'];
        yield 'Select' => ['Select', 'components/select.html.twig'];

        // Two words
        yield 'AlertDialog' => ['AlertDialog', 'components/alert-dialog.html.twig'];
        yield 'AspectRatio' => ['AspectRatio', 'components/aspect-ratio.html.twig'];
        yield 'ButtonGroup' => ['ButtonGroup', 'components/button-group.html.twig'];
        yield 'CardContent' => ['CardContent', 'components/card-content.html.twig'];
        yield 'CardFooter' => ['CardFooter', 'components/card-footer.html.twig'];
        yield 'CardHeader' => ['CardHeader', 'components/card-header.html.twig'];
        yield 'ContextMenu' => ['ContextMenu', 'components/context-menu.html.twig'];
        yield 'DataTable' => ['DataTable', 'components/data-table.html.twig'];
        yield 'DropdownMenu' => ['DropdownMenu', 'components/dropdown-menu.html.twig'];
        yield 'HoverCard' => ['HoverCard', 'components/hover-card.html.twig'];
        yield 'InputGroup' => ['InputGroup', 'components/input-group.html.twig'];
        yield 'NativeSelect' => ['NativeSelect', 'components/native-select.html.twig'];
        yield 'RadioGroup' => ['RadioGroup', 'components/radio-group.html.twig'];
        yield 'ScrollArea' => ['ScrollArea', 'components/scroll-area.html.twig'];
        yield 'TabsContent' => ['TabsContent', 'components/tabs-content.html.twig'];
        yield 'ToggleGroup' => ['ToggleGroup', 'components/toggle-group.html.twig'];
        yield 'TableCell' => ['TableCell', 'components/table-cell.html.twig'];
        yield 'TableHeader' => ['TableHeader', 'components/table-header.html.twig'];
        yield 'TableRow' => ['TableRow', 'components/table-row.html.twig'];
        yield 'SidebarItem' => ['SidebarItem', 'components/sidebar-item.html.twig'];
        yield 'SidebarSection' => ['SidebarSection', 'components/sidebar-section.html.twig'];

        // Three words
        yield 'DropdownMenuItem' => ['DropdownMenuItem', 'components/dropdown-menu-item.html.twig'];
        yield 'DropdownMenuLabel' => ['DropdownMenuLabel', 'components/dropdown-menu-label.html.twig'];
        yield 'DropdownMenuSeparator' => ['DropdownMenuSeparator', 'components/dropdown-menu-separator.html.twig'];
        yield 'NavigationMenu' => ['NavigationMenu', 'components/navigation-menu.html.twig'];
    }
}
