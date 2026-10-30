<?php

namespace App\Domain\Content;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Inline\Text;

/**
 * Images may only point at inert sources: uploaded media, bundled assets or
 * inline image data. Any other image (for example a same-site account route)
 * is replaced with its alt text, so viewing content never sends a request that
 * changes the viewer's state.
 */
final class InertImageExtension implements ExtensionInterface
{
    public function __construct(private string $host) {}

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addEventListener(DocumentParsedEvent::class, function (DocumentParsedEvent $event): void {
            $blocked = [];
            foreach ($event->getDocument()->iterator() as $node) {
                if ($node instanceof Image && ! self::allowed($node->getUrl(), $this->host)) {
                    $blocked[] = $node;
                }
            }
            foreach ($blocked as $image) {
                $alt = '';
                foreach ($image->iterator() as $child) {
                    if ($child instanceof Text) {
                        $alt .= $child->getLiteral();
                    }
                }
                $image->replaceWith(new Text($alt));
            }
        });
    }

    public static function allowed(string $url, string $host): bool
    {
        if (preg_match('#^data:image/(png|gif|jpeg|webp);base64,[A-Za-z0-9+/=]+$#', $url) === 1) {
            return true;
        }
        $parts = parse_url($url);
        if ($parts === false || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['fragment'])) {
            return false;
        }
        if (isset($parts['scheme']) || isset($parts['host'])) {
            if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || strcasecmp($parts['host'] ?? '', $host) !== 0) {
                return false;
            }
        }
        $path = $parts['path'] ?? '';
        $query = $parts['query'] ?? null;
        if (preg_match('#^/media/[0-9]{1,18}$#', $path) === 1) {
            return $query === null || preg_match('/^v=[0-9a-f]{1,64}$/', $query) === 1;
        }

        return $query === null && ! str_contains($path, '..') && preg_match('#^/assets/[A-Za-z0-9._/-]{1,200}$#', $path) === 1;
    }
}
