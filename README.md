# hgabka/i18n-routing-bundle

A [JMSI18nRoutingBundle](https://github.com/schmittjoh/JMSI18nRoutingBundle) fork-ja Symfony 7/8 (PHP 8.2+) támogatással. Többnyelvű útvonalakat tesz lehetővé: a meglévő route-jaidat nem kell módosítani, a bundle minden nyelvhez létrehozza az útvonal nyelvi változatát (nyelv-előtaggal, hosztonként vagy lefordított útvonal-mintákkal), és a `router` szolgáltatást a saját `I18nRouter`-ére cseréli.

A névtér és a konfigurációs gyökér az eredetit követi (`JMS\I18nRoutingBundle`, `jms_i18n_routing`), így az upstream dokumentáció érvényes maradt; a lényeg alább, a teljes leírás: [Resources/doc/index.rst](Resources/doc/index.rst).

## Telepítés

```bash
composer require hgabka/i18n-routing-bundle
```

```php
// config/bundles.php
JMS\I18nRoutingBundle\JMSI18nRoutingBundle::class => ['all' => true],
```

Opcionális: a `jms/translation-bundle` az útvonalak fordítási kulcsainak kinyeréséhez (`bin/console translation:extract <nyelv> --enable-extractor=jms_i18n_routing`).

## Konfiguráció

```yaml
# config/packages/jms_i18n_routing.yaml
jms_i18n_routing:
    default_locale: hu            # kötelező, a `locales` egyike
    locales: [hu, en]             # vagy "hu, en"
    strategy: prefix_except_default   # prefix | prefix_except_default | custom (alapértelmezés)
    catalogue: routes             # fordítási katalógus az útvonalakhoz
    redirect_to_host: true        # hosztonkénti nyelvnél átirányít a megfelelő hosztra
    hosts:                        # opcionális: nyelvenként külön hoszt (minden nyelvhez kötelező, hosztok nem ismétlődhetnek)
        hu: example.hu
        en: example.com
    cookie:                       # a választott nyelv megjegyzése
        enabled: true
        name: hl
        lifetime: 31536000
        path: /
        domain: ~
        secure: false
        httponly: false
```

Stratégiák és az eredő URL-ek (`/kapcsolat` ↔ `/contact` fordítás esetén):

| `strategy` | Eredmény |
|---|---|
| `prefix` | `/hu/kapcsolat`, `/en/contact` |
| `prefix_except_default` | `/kapcsolat`, `/en/contact` (az alapnyelv előtag nélkül) |
| `custom` | nincs automatikus előtag; a nyelvi mintákat te adod meg fordításokkal, vagy a `prefix_with_locale` / `omit_prefix_when_default` opciókkal szabályozod |
| `hosts` megadva | minden nyelv külön hoszton, pl. `example.hu/kapcsolat`, `example.com/contact` |

Stratégiaváltás után törölni kell a cache-t. Ha egy útvonalat nem akarsz lefordítani, nevezd át `_`-lel kezdődőre (pl. `_contact`), vagy kapcsold ki a route `options: { i18n: false }` beállításával.

## Működés

- `Router\I18nLoader` a route-gyűjteményből nyelvenként előállítja az útvonalakat; a minták előállítását a `PatternGenerationStrategyInterface` (`DefaultPatternGenerationStrategy`), a kihagyandó route-okat a `RouteExclusionStrategyInterface` (`DefaultRouteExclusionStrategy`) határozza meg – mindkettő cserélhető.
- `Router\I18nRouter` (a `router` szolgáltatás helyett, `DependencyInjection\Compiler\SetRouterPass`) illeszt és generál; link generáláskor a nyelv sorrendben: a `generate()`-nek átadott `_locale` paraméter, a request context `_locale`-ja, majd a `default_locale`. Pl. `{{ path('homepage', {'_locale': 'en'}) }}`.
- `Router\DefaultLocaleResolver` (`LocaleResolverInterface`) határozza meg a kérés nyelvét; a `I18nRouter` ha a kért nyelv nem elfogadható, `Exception\NotAcceptableLanguageException`-t dob.
- `EventListener\LocaleChoosingListener` – a nyelv kiválasztása (Accept-Language, cookie) azokon az URL-eken, amelyek nem hordoznak nyelvet; `EventListener\CookieSettingListener` – a választott nyelv cookie-ba írása.
- `Controller\RedirectController` – hosztok közti átirányításhoz.
- `Translation\RouteTranslationExtractor` – a JMS Translation Bundle extractora (`jms_i18n_routing`).

## Tesztek

```bash
composer install
vendor/bin/phpunit
```

## Licenc

Apache-2.0 (az eredeti JMSI18nRoutingBundle licence szerint), lásd: `Resources/meta/LICENSE`.
