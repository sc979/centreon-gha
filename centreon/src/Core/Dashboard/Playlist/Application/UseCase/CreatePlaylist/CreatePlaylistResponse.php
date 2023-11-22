<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Core\Dashboard\Playlist\Application\UseCase\CreatePlaylist;

final class CreatePlaylistResponse
{
    public int $id = 0;

    public string $name = '';

    public ?string $description = null;

    public int $rotationTime = 10;

    /** @var array{}|array<array{id: int, order:int}> */
    public array $dashboards = [];

    public bool $isPublic = false;

    /** @var array{}|array{id: int, name: string} */
    public array $author = [];

    public \DateTimeImmutable $createdAt;
}