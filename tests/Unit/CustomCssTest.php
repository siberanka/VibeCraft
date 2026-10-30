<?php

namespace Tests\Unit;

use App\Core\Theme\CustomCss;
use App\Support\SiteLink;
use Tests\TestCase;

class CustomCssTest extends TestCase
{
    public function test_ordinary_css_compiles_without_comments(): void
    {
        $result = CustomCss::compile("/* hero */\n.stage-title { color: var(--c-accent); }\n@media (max-width: 600px) { .stage-title { font-size: 2rem; } }\n.x { background: url('/media/12') }");

        $this->assertSame([], $result['errors']);
        $this->assertStringNotContainsString('hero', $result['css']);
        $this->assertStringContainsString("url('/media/12')", $result['css']);
    }

    public function test_dangerous_css_is_refused(): void
    {
        foreach ([
            '@import "https://evil.example/x.css";',
            '.a { background: url(https://evil.example/track?x) }',
            '.a { background: url(//evil.example/x) }',
            '.a { background: url(/media/../admin) }',
            '.a { width: expression(alert(1)) }',
            '.a { background: url(javascript:alert(1)) }',
            '.a { -moz-binding: x }',
            '.a { content: "\75rl" }',
            '</style><script>alert(1)</script>',
            '.a { color: red',
            '.a { content: "open }',
            '@font-face { font-family: x }',
            '.a { background: image-set("/media/1" 1x) }',
            '.a { color: red } /* open',
            "@im/**/port 'x';",
            'u/**/rl(https://evil.example)',
        ] as $css) {
            $this->assertNotSame([], CustomCss::compile($css)['errors'], $css);
        }
    }

    public function test_comment_tricks_cannot_rebuild_a_blocked_token(): void
    {
        $result = CustomCss::compile('.a { background: u/**/rl(https://evil.example) }');

        $this->assertNotSame([], $result['errors']);
        $this->assertSame('', $result['css']);
    }

    public function test_menu_links_are_same_site_paths_or_https(): void
    {
        foreach (['/rules', '/store?category=ranks', 'https://discord.gg/example', 'https://example.com/a#b'] as $ok) {
            $this->assertTrue(SiteLink::valid($ok), $ok);
        }
        foreach (['javascript:alert(1)', '//evil.example', 'http://example.com', 'https://user:pw@example.com', '/a b', 'rules', 'https://', "/a\nb", 'data:text/html,x', '/\\evil.example', 'HTTPS://example.com'] as $bad) {
            $this->assertFalse(SiteLink::valid($bad), $bad);
        }
    }
}
