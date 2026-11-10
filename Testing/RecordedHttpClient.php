<?php

namespace Omnimap\Testing;

/**
 * An HTTP client that answers from what it was given - a callable of the
 * method, the URL and the options, returning [status, body, headers] - and
 * keeps every request made ($requests): a provider's calls checked with no
 * network. omnisense/contracts' RecordedHttpClient, under omnimap's name
 * (routes() by pattern, header(), and stream(), which it now answers).
 */
final class RecordedHttpClient extends \Omnisense\Contracts\Testing\RecordedHttpClient
{
}
