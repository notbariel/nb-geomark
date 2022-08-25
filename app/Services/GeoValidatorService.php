<?php

namespace App\Services;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\DomCrawler\Crawler;

class GeoValidatorService
{
    protected CountryShortcodeService $countryService;
    protected string $mapboxAccessToken;

    const GEOCODING_URL = 'https://api.mapbox.com/geocoding/v5/mapbox.places/%s.json';

    protected Uri $url;
    protected string $html;
    protected Crawler $crawler;

    protected Crawler $title;
    protected Crawler $favicon;
    protected Crawler $position;
    protected Crawler $region;
    protected Crawler $placename;
    protected Crawler $icbm;
    protected Crawler $dctitle;

    protected array $metrics;

    const POSITION = 'geo.position';
    const REGION = 'geo.region';
    const PLACENAME = 'geo.placename';
    const ICBM = 'ICBM';
    const DCTITLE = 'DC.title';
    const PLAUSIBILITY = 'plausibility';

    const MUST_BE_VALID = [
        self::POSITION,
        self::REGION,
        self::PLACENAME,
        self::PLAUSIBILITY,
    ];

    protected array $results;

    public function __construct(
        CountryShortcodeService $countryService,
        Crawler $crawler,
        $mapboxAccessToken
    ) {
        $this->countryService = $countryService;

        $this->crawler = $crawler;

        $this->mapboxAccessToken = $mapboxAccessToken;

        $this->metrics = [
            self::POSITION => [
                'context' => '',
                'errors' => [],
                'data' => [],
                'is_valid' => false,
            ],
            self::REGION => [
                'context' => '',
                'errors' => [],
                'data' => [],
                'is_valid' => false,
            ],
            self::PLACENAME => [
                'context' => '',
                'errors' => [],
                'data' => [],
                'is_valid' => false,
            ],
            self::ICBM => [
                'context' => '',
                'errors' => [],
                'data' => [],
                'is_valid' => false,
            ],
            self::DCTITLE => [
                'context' => '',
                'errors' => [],
                'data' => [],
                'is_valid' => false,
            ],
            self::PLAUSIBILITY => [
                'context' => '',
                'errors' => [],
                'data' => [],
                'is_valid' => false,
            ],
        ];
    }

    public function validate($url): self
    {
        try {
            $this
                ->setUrl($url)
                ->getHtml()
                ->extractHtmlTags()
                ->validatePosition()
                ->validateRegion()
                ->validatePlacename()
                ->validateICBM()
                ->validateDCTitle()
                ->validatePlausibility();

            $isSuccessful = true;
            foreach (self::MUST_BE_VALID as $metric) {
                if (!$this->metricIsValid($metric)) {
                    $isSuccessful = false;
                    break;
                }
            }

            $this->setResults([
                'title' => $this->title->text(),
                'favicon' => $this->getFavicon(),
                'url' => $this->url,
                'metrics' => $this->metrics,
                'is_successful' => $isSuccessful,
            ]);
        } catch (\Exception $e) {
            $this->setResults([
                'is_halted' => true,
                'halted_msg' => $e->getMessage()
            ]);
        }

        return $this;
    }

    protected function getFavicon(): mixed
    {
        $url = null;
        if ($this->favicon->count()) {
            $href = $this->favicon->first()->extract(['href'])[0];

            if ($href) {
                $url = UriResolver::resolve($this->url, new Uri($href));
            }
        }

        return $url;
    }

    protected function setResults(array $data = []): void
    {
        $this->results = $data;
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function setUrl($url): self
    {
        $validator = Validator::make([
            'url' => $url
        ], [
            'url' => 'url'
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException('Invalid url.');
        }

        $this->url = new Uri($url);

        return $this;
    }

    protected function getHtml(): self
    {
        $response = Http::get($this->url);

        if ($response->failed()) {
            // throw $response->toException();
            throw new \InvalidArgumentException("Could not access URL.");
        }

        $this->html = $response->body();

        if (!$this->html) {
            throw new \InvalidArgumentException("HTML is empty.");
        }

        $this->crawler->add($this->html);

        return $this;
    }

    protected function extractHtmlTags(): self
    {
        $this->title = $this->crawler->filterXPath('//head//title');
        $this->favicon = $this->crawler->filterXPath('//head//link[contains(@rel, "icon")]');
        $this->position = $this->crawler->filterXPath('//head//meta[@name="' . self::POSITION . '"]');
        $this->region = $this->crawler->filterXPath('//head//meta[@name="' . self::REGION . '"]');
        $this->placename = $this->crawler->filterXPath('//head//meta[@name="' . self::PLACENAME . '"]');
        $this->icbm = $this->crawler->filterXPath('//head//meta[@name="' . self::ICBM . '"]');
        $this->dctitle = $this->crawler->filterXPath('//head//meta[@name="' . self::DCTITLE . '"]');

        return $this;
    }

    protected function validatePosition(): self
    {
        if ($this->position->count()) {
            $tag = $this->position->first();

            $this->setMetricContext(self::POSITION, $tag->outerHtml());

            $content = $tag->extract(['content'])[0];

            $coordinates = array_map('trim', explode(";", $content));

            // Lat: $coordinates[0]
            if (isset($coordinates[0]) && $coordinates[0] != '') {
                $this->addMetricData(self::POSITION, 'lat', $coordinates[0]);

                if (!$this->isValidLat($coordinates[0])) {
                    $this->addMetricError(self::POSITION, 'Invalid "Latitude" value.');
                }
            } else {
                $this->addMetricError(self::POSITION, 'No "Latitude" value.');
            }

            // Lng: $coordinates[1]
            if (isset($coordinates[1]) && $coordinates[1] != '') {
                $this->addMetricData(self::POSITION, 'lng', $coordinates[1]);

                if (!$this->isValidLng($coordinates[1])) {
                    $this->addMetricError(self::POSITION, 'Invalid "Longitude" value.');
                }
            } else {
                $this->addMetricError(self::POSITION, 'No "Longitude" value.');
            }
        } else {
            $this->addMetricError(self::POSITION, 'Could not find "' . self::POSITION . '" tag.');
        }

        if (count($this->getMetricErrors(self::POSITION)) === 0) {
            $this->setMetricValidity(self::POSITION, true);
        }

        return $this;
    }

    protected function validateRegion(): self
    {
        if ($this->region->count()) {
            $tag = $this->region->first();

            $this->setMetricContext(self::REGION, $tag->outerHtml());

            $content = $tag->extract(['content'])[0];

            $shortcodes = array_map('trim', explode("-", $content));

            // country: $shortcodes[0]
            if (isset($shortcodes[0]) && $shortcodes[0] != '') {
                $this->addMetricData(self::REGION, 'country_shortcode', $shortcodes[0]);

                $countryName = $this->countryService->getCountryByShortcode($shortcodes[0]);

                $this->addMetricData(self::REGION, 'country', $countryName);

                if (!$countryName) {
                    $this->addMetricError(self::REGION, 'Invalid "Country" value.');
                }
            } else {
                $this->addMetricError(self::REGION, 'No "Country" value.');
            }

            // region: $shortcodes[1]
            if (isset($shortcodes[1]) && $shortcodes[1] != '') {
                $this->addMetricData(self::REGION, 'region_shortcode', $shortcodes[1]);

                $regionName = $this->countryService->getRegionByShortcode($shortcodes[0], $shortcodes[1]);

                $this->addMetricData(self::REGION, 'region', $regionName);

                if (!$regionName) {
                    $this->addMetricError(self::REGION, 'Invalid "Region" value.');
                }
            } else {
                $this->addMetricError(self::REGION, 'No "Region" value.');
            }
        } else {
            $this->addMetricError(self::REGION, 'Could not find "' . self::REGION . '" tag.');
        }

        if (count($this->getMetricErrors(self::REGION)) === 0) {
            $this->setMetricValidity(self::REGION, true);
        }

        return $this;
    }

    protected function validatePlacename(): self
    {
        if ($this->placename->count()) {
            $tag = $this->placename->first();

            $this->setMetricContext(self::PLACENAME, $tag->outerHtml());

            $content = $tag->extract(['content'])[0];

            $this->addMetricData(self::PLACENAME, 'content', $content);

            if (!isset($content) || $content == '') {
                $this->addMetricError(self::PLACENAME, 'No "Placename" value.');
            }
        } else {
            $this->addMetricError(self::PLACENAME, 'Could not find "' . self::PLACENAME . '" tag.');
        }

        if (count($this->getMetricErrors(self::PLACENAME)) === 0) {
            $this->setMetricValidity(self::PLACENAME, true);
        }

        return $this;
    }

    protected function validateICBM(): self
    {
        if ($this->icbm->count()) {
            $tag = $this->icbm->first();

            $this->setMetricContext(self::ICBM, $tag->outerHtml());

            $content = $tag->extract(['content'])[0];

            $coordinates = array_map('trim', explode(",", $content));

            // Lat: $coordinates[0]
            if (isset($coordinates[0]) && $coordinates[0] != '') {
                $this->addMetricData(self::ICBM, 'lat', $coordinates[0]);

                if (!$this->isValidLat($coordinates[0])) {
                    $this->addMetricError(self::ICBM, 'Invalid "Latitude" value.');
                }

                if (
                    $this->metricIsValid(self::POSITION) &&
                    !$this->matchesMetricData(self::POSITION, 'lat', $coordinates[0])
                ) {
                    $this->addMetricError(self::ICBM, '"Latitude" does not match "' . self::POSITION . '".');
                }
            } else {
                $this->addMetricError(self::ICBM, 'No "Latitude" value.');
            }

            // Lng: $coordinates[1]
            if (isset($coordinates[1]) && $coordinates[1] != '') {
                $this->addMetricData(self::ICBM, 'lng', $coordinates[1]);

                if (!$this->isValidLng($coordinates[1])) {
                    $this->addMetricError(self::ICBM, 'Invalid "Longitude" value.');
                }

                if (
                    $this->metricIsValid(self::POSITION) &&
                    !$this->matchesMetricData(self::POSITION, 'lng', $coordinates[1])
                ) {
                    $this->addMetricError(self::ICBM, '"Longitude" does not match "' . self::POSITION . '".');
                }
            } else {
                $this->addMetricError(self::ICBM, 'No "Longitude" value.');
            }
        } else {
            $this->addMetricError(self::ICBM, 'Could not find "' . self::ICBM . '" tag.');
        }

        if (count($this->getMetricErrors(self::ICBM)) === 0) {
            $this->setMetricValidity(self::ICBM, true);
        }

        return $this;
    }

    protected function validateDCTitle(): self
    {
        if ($this->dctitle->count()) {
            $tag = $this->dctitle->first();

            $this->setMetricContext(self::DCTITLE, $tag->outerHtml());

            $content = $tag->extract(['content'])[0];

            $this->addMetricData(self::DCTITLE, 'content', $content);

            if (!isset($content) || $content == '') {
                $this->addMetricError(self::DCTITLE, 'No "DC.Title" value.');
            }
        } else {
            $this->addMetricError(self::DCTITLE, 'Could not find "' . self::DCTITLE . '" tag.');
        }

        if (count($this->getMetricErrors(self::DCTITLE)) === 0) {
            $this->setMetricValidity(self::DCTITLE, true);
        }

        return $this;
    }

    protected function validatePlausibility(): self
    {
        if ($this->metricIsValid(self::POSITION) && $this->metricIsValid(self::REGION)) {
            $placename = $this->getMetricData(self::PLACENAME, 'content');
            $region = $this->getMetricData(self::REGION, 'region');
            $country = $this->getMetricData(self::REGION, 'country');

            $query = implode(' ', [$placename, $region, $country]);

            $this->addMetricData(self::PLAUSIBILITY, 'query', $query);

            $response = Http::get(sprintf(self::GEOCODING_URL, $query), [
                'language' => "en-US",
                'access_token' => $this->mapboxAccessToken,
                'limit' => 1,
            ]);

            if ($response->failed()) {
                // throw $response->toException();
                throw new \InvalidArgumentException("Geocoding error.");
            }

            $feature = $response->collect()->get('features')[0];

            if ($feature) {
                $this->addMetricData(self::PLAUSIBILITY, 'feature', $feature);

                $lng1 = $this->getMetricData(self::POSITION, 'lng');
                $lat1 = $this->getMetricData(self::POSITION, 'lat');
                $lng2 = $feature['center'][0];
                $lat2 = $feature['center'][1];

                // calculate
                $distance = $this->calculateDistance($lat1, $lng1, $lat2, $lng2);
                $distanceInKm = $distance / 1000;

                $this->addMetricData(self::PLAUSIBILITY, 'distance', number_format($distanceInKm) . 'km');

                if ($distanceInKm > 25) {
                    $this->addMetricError(self::PLAUSIBILITY, 'The position seems too far away from the geocoding result.');
                }
            } else {
                $this->addMetricError(self::PLAUSIBILITY, 'Geocoding error.');
            }
        } else {
            $this->addMetricError(self::PLAUSIBILITY, 'Could not perform plausibility check.');
        }

        if (count($this->getMetricErrors(self::PLAUSIBILITY)) === 0) {
            $this->setMetricValidity(self::PLAUSIBILITY, true);
        }

        return $this;
    }

    protected function addMetricError($metric, $msg): self
    {
        $this->metrics[$metric]['errors'][] = $msg;

        return $this;
    }

    protected function getMetricErrors($metric): array
    {
        return $this->metrics[$metric]['errors'];
    }

    protected function addMetricData($metric, $key, $value): self
    {
        $this->metrics[$metric]['data'][$key] = $value;

        return $this;
    }

    protected function getAllMetricData($metric): array
    {
        return $this->metrics[$metric]['data'];
    }

    protected function getMetricData($metric, $key): mixed
    {
        return isset($this->metrics[$metric]['data'][$key]) ?
            $this->metrics[$metric]['data'][$key] :
            null;
    }

    protected function setMetricValidity($metric, bool $value): void
    {
        $this->metrics[$metric]['is_valid'] = $value;
    }

    protected function setMetricContext($metric, string $value): void
    {
        $this->metrics[$metric]['context'] = $value;
    }

    protected function isValidLat($lat): bool
    {
        return is_numeric($lat) && abs($lat) <= 90;
    }

    protected function isValidLng($lng): bool
    {
        return is_numeric($lng) && abs($lng) <= 180;
    }

    protected function metricIsValid($metric): bool
    {
        return $this->metrics[$metric]['is_valid'];
    }

    protected function matchesMetricData($metric, $key, $value): bool
    {
        return $this->metrics[$metric]['data'][$key] === $value;
    }

    protected function calculateDistance($lat1, $lng1, $lat2, $lng2, $radius = 6378137): float
    {
        static $x = M_PI / 180;
        $lat1 *= $x;
        $lng1 *= $x;
        $lat2 *= $x;
        $lng2 *= $x;
        $distance = 2 * asin(sqrt(pow(sin(($lat1 - $lat2) / 2), 2) + cos($lat1) * cos($lat2) * pow(sin(($lng1 - $lng2) / 2), 2)));

        return $distance * $radius;
    }
}
