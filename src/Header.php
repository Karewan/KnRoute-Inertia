<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia;

/** Headers of the Inertia protocol. */
final class Header
{
	/** Request: sent by the client on every visit. Response: marks a page object response. */
	public const string INERTIA = 'X-Inertia';

	/** Request: asset version of the client. Response: current version, on a version mismatch. */
	public const string VERSION = 'X-Inertia-Version';

	/** Response: target of a full page visit, with a 409 status. */
	public const string LOCATION = 'X-Inertia-Location';

	/** Response: target of an Inertia visit whose URL has a fragment, with a 409 status. */
	public const string REDIRECT = 'X-Inertia-Redirect';

	/** Request: component of a partial reload. */
	public const string PARTIAL_COMPONENT = 'X-Inertia-Partial-Component';

	/** Request: comma-separated props selected by a partial reload. */
	public const string PARTIAL_ONLY = 'X-Inertia-Partial-Data';

	/** Request: comma-separated props excluded by a partial reload. */
	public const string PARTIAL_EXCEPT = 'X-Inertia-Partial-Except';

	/** Request: comma-separated props replaced instead of merged. */
	public const string RESET = 'X-Inertia-Reset';

	/** Request: error bag scoping the validation errors. */
	public const string ERROR_BAG = 'X-Inertia-Error-Bag';

	/** Request: comma-separated once props the client already holds. */
	public const string EXCEPT_ONCE_PROPS = 'X-Inertia-Except-Once-Props';

	/** Request: "prepend" when an infinite scroll loads the previous page. */
	public const string INFINITE_SCROLL_MERGE_INTENT = 'X-Inertia-Infinite-Scroll-Merge-Intent';

	/** Request and response: marks a Precognition validation request and its response. */
	public const string PRECOGNITION = 'Precognition';

	/** Request: comma-separated fields validated by a Precognition request. */
	public const string PRECOGNITION_VALIDATE_ONLY = 'Precognition-Validate-Only';

	/** Response: a Precognition request passed the validation. */
	public const string PRECOGNITION_SUCCESS = 'Precognition-Success';
}
