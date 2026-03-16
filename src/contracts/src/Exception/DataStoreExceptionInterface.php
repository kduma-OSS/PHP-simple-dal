<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Contracts\Exception;

/**
 * Marker interface for all Simple DAL exceptions.
 * Allows broad catch: catch (DataStoreExceptionInterface $e)
 */
interface DataStoreExceptionInterface extends \Throwable {}
