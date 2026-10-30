# Third-party software and research boundaries

VibeCraft's own code, including the website and the game plugin in `bridge/paper/`, is licensed under
the VibeCraft License in LICENSE (PolyForm Noncommercial License 1.0.0 with additional terms: free to run
for your own servers, including commercial ones, but not to be sold or resold under any name). Third-party
components keep their own licenses as described below. Dependency licenses apply to their
respective files and remain with distributions. The Composer lock file records exact dependency
versions and upstream repositories; do not replace these with unpinned runtime CDN packages.

The application skeleton and Laravel framework are MIT software by Taylor Otwell and Laravel
contributors: https://github.com/laravel/laravel and https://github.com/laravel/framework.
Laravel Fortify is MIT software by Taylor Otwell and its contributors:
https://github.com/laravel/fortify. Permission management is MIT software by Spatie and its
contributors: https://github.com/spatie/laravel-permission. Preserve their license notices in vendor.

Other bundled Composer libraries retain their upstream license files, including Symfony
components, League CommonMark/Flysystem, Carbon, Guzzle, PSR interfaces and BaconQrCode.
Development tools are not intended to be included in production packages.

Locally served fonts retain their upstream OFL files alongside the font assets. The notices
name the font designers and reserved font names; redistribution does not transfer their trademarks.

Public documentation of other community platforms informed general feature requirements.
No third-party brand, design, template, asset or source code is bundled with VibeCraft, and no
license to any third-party paid theme or private implementation is implied.

VibeCraft is an independent project and is not an official Minecraft product. It is not approved
by or associated with Mojang or Microsoft. Operators must review current Minecraft commercial
usage rules and their own payment, privacy and consumer obligations before selling benefits.
