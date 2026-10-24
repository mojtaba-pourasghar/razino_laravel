<?php

namespace App\Console\Commands;

use App\Models\Chapter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Publishes a chapter package built by the game repository's pack_chapter.py.
 *
 * The package is the truth. This command reads what it says about itself and
 * records it, rather than letting the panel describe a chapter it has not
 * seen — a catalogue that disagrees with the package it points at is how a
 * player ends up downloading a chapter the game then refuses.
 */
class ImportChapterPackage extends Command
{
    protected $signature = 'razino:import-chapter
        {path : the packed chapter directory, holding manifest.json}
        {--publish : list it in the catalogue straight away}
        {--price= : price in toman for a paid chapter}';

    protected $description = 'Import a signed chapter package and record it in the catalogue';

    public function handle(): int
    {
        $path = rtrim($this->argument('path'), '/');
        $manifestPath = $path.'/manifest.json';

        if (! File::exists($manifestPath)) {
            $this->error("no manifest.json at {$manifestPath}");

            return self::FAILURE;
        }

        $manifest = json_decode(File::get($manifestPath), true);
        if (! is_array($manifest)) {
            $this->error('manifest.json is not readable JSON');

            return self::FAILURE;
        }

        // A package with no signature can only have come from a development
        // build. Publishing one would mean the game refuses it on download,
        // which looks to a player like the chapter is broken.
        if (empty($manifest['signature'])) {
            $this->error('this package is unsigned — the game will refuse it');

            return self::FAILURE;
        }

        $chapterId = (string) ($manifest['chapter_id'] ?? '');
        if ($chapterId === '') {
            $this->error('the manifest names no chapter');

            return self::FAILURE;
        }

        // Every file the manifest promises has to actually be there, and be
        // the size it claims. Finding that out here is cheap; finding it out
        // on a player's phone is a failed download.
        $bytes = 0;
        foreach ($manifest['files'] ?? [] as $file) {
            $full = $path.'/'.$file['path'];
            if (! File::exists($full)) {
                $this->error("the manifest lists {$file['path']}, which is not in the package");

                return self::FAILURE;
            }
            $size = File::size($full);
            if ($size !== (int) $file['size']) {
                $this->error("{$file['path']} is {$size} bytes, the manifest says {$file['size']}");

                return self::FAILURE;
            }
            $bytes += $size;
        }

        $story = $this->readStory($path);

        $chapter = Chapter::updateOrCreate(
            ['chapter_id' => $chapterId],
            [
                'title' => $manifest['title'] ?? $story['title'] ?? $chapterId,
                'subtitle' => $story['subtitle'] ?? null,
                'synopsis' => $story['synopsis'] ?? null,
                'language' => $manifest['language'] ?? 'fa',
                'version' => (int) ($manifest['version'] ?? 1),
                'minimum_game_version' => $manifest['minimum_game_version'] ?? '1.0.0',
                'scene_count' => count($story['scenes'] ?? []),
                'episode_count' => count($story['episodes'] ?? []),
                'stage_count' => count($story['stages'] ?? []),
                'clue_count' => count($story['clues'] ?? []),
                'package_sha256' => hash('sha256', File::get($manifestPath)),
                'package_bytes' => $bytes,
            ]
        );

        if ($this->option('price') !== null) {
            $chapter->price_toman = (int) $this->option('price');
            $chapter->access_type = $chapter->price_toman > 0 ? 'paid' : 'free';
        }
        if ($this->option('publish')) {
            $chapter->is_published = true;
        }
        $chapter->save();

        $this->info(sprintf(
            '%s v%d — %d scenes, %d stages, %d clues, %s%s',
            $chapterId,
            $chapter->version,
            $chapter->scene_count,
            $chapter->stage_count,
            $chapter->clue_count,
            $this->formatBytes($bytes),
            $chapter->is_published ? ', published' : ', not published yet'
        ));

        if ($chapter->stage_count === 0) {
            $this->warn('  this chapter has no stages — it cannot be played the way the design expects');
        }

        return self::SUCCESS;
    }

    /** The story, for the counts the catalogue shows. */
    private function readStory(string $path): array
    {
        $storyPath = $path.'/story.json';
        if (! File::exists($storyPath)) {
            return [];
        }
        $story = json_decode(File::get($storyPath), true);

        return is_array($story) ? $story : [];
    }

    private function formatBytes(int $bytes): string
    {
        return $bytes >= 1048576
            ? sprintf('%.1f MiB', $bytes / 1048576)
            : sprintf('%.0f KiB', $bytes / 1024);
    }
}
