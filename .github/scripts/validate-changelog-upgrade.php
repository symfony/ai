<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * Validates that a pull request's CHANGELOG.md / UPGRADE.md changes are consistent
 * with its frontmatter table (".github/PULL_REQUEST_TEMPLATE.md") and labels.
 *
 * Enforced rules:
 *   1. Feature => changelog: "New feature? = yes" requires a new entry in a CHANGELOG.md
 *      of a component/bridge the PR actually touched.
 *   2. Bug-only => no md changes: "Bug fix? = yes" + "New feature? = no" forbids any
 *      CHANGELOG.md / UPGRADE.md modification, unless the PR carries the "BC Break"
 *      label (a BC-breaking bug fix still documents the break per rules 3-4).
 *   3. "BC Break" label => UPGRADE.md must gain an entry in the upcoming section.
 *   4. UPGRADE.md gained an entry => the PR must carry the "BC Break" label.
 *   5. Upcoming-section only: a version section whose version <= the latest git tag is
 *      frozen; modifying it is an error. (No tags yet => nothing is frozen.)
 *
 * Inputs are passed via environment variables (populated by the workflow):
 *   PR_BODY    Pull request description (markdown).
 *   PR_LABELS  JSON array of label names.
 *   BASE_SHA   Base commit to diff against.
 *   HEAD_SHA   Optional; defaults to the checked-out HEAD (the PR merge commit in CI).
 *
 * Emits GitHub Actions "::error::" / "::warning::" annotations and exits non-zero on failure.
 */

const BC_BREAK_LABEL = 'BC Break';

/** @return list<string> */
function git(string $command): array
{
    exec($command.' 2>/dev/null', $output, $code);

    return $output;
}

/**
 * Reads a file at a given revision. Returns an empty string when the file does
 * not exist at that revision (e.g. newly added files have no base content).
 */
function fileAtRevision(string $sha, string $path): string
{
    exec(sprintf('git show %s:%s 2>/dev/null', escapeshellarg($sha), escapeshellarg($path)), $output, $code);
    if (0 !== $code) {
        return '';
    }

    return implode("\n", $output);
}

/**
 * Splits a CHANGELOG.md / UPGRADE.md into sections keyed by version.
 *
 * For CHANGELOG.md the heading is a bare version ("0.10") underlined with dashes.
 * For UPGRADE.md the heading is "UPGRADE FROM x to y" underlined with "=" and the
 * section is keyed by the "to" version.
 *
 * @return array<string, list<string>> version => content lines of that section
 */
function parseSections(string $content, string $kind): array
{
    if ('' === $content) {
        return [];
    }

    $lines = explode("\n", $content);
    $sections = [];
    $current = null;

    for ($i = 0, $count = \count($lines); $i < $count; ++$i) {
        $line = $lines[$i];
        $next = $lines[$i + 1] ?? '';

        $version = null;
        if ('changelog' === $kind) {
            if (1 === preg_match('/^(\d+\.\d+(?:\.\d+)?)\s*$/', $line, $m) && 1 === preg_match('/^-{2,}\s*$/', $next)) {
                $version = $m[1];
            }
        } else {
            if (1 === preg_match('/^UPGRADE FROM \S+ to (\S+)\s*$/', $line, $m) && 1 === preg_match('/^={2,}\s*$/', $next)) {
                $version = $m[1];
            }
        }

        if (null !== $version) {
            $current = $version;
            $sections[$current] = [];
            ++$i; // skip the underline line
            continue;
        }

        if (null !== $current) {
            $sections[$current][] = $line;
        }
    }

    return $sections;
}

/**
 * Returns the added bullet lines of a section between base and head content.
 *
 * @param list<string> $baseLines
 * @param list<string> $headLines
 *
 * @return list<string>
 */
function addedBullets(array $baseLines, array $headLines): array
{
    $baseBullets = [];
    foreach ($baseLines as $line) {
        if (1 === preg_match('/^\s*\*\s+\S/', $line)) {
            $baseBullets[trim($line)] = true;
        }
    }

    $added = [];
    foreach ($headLines as $line) {
        if (1 === preg_match('/^\s*\*\s+\S/', $line) && !isset($baseBullets[trim($line)])) {
            $added[] = trim($line);
        }
    }

    return $added;
}

/**
 * Parses "x.y" or "x.y.z" (optionally "vx.y.z") into a comparable [major, minor] tuple.
 *
 * @return array{int, int}|null
 */
function minorTuple(string $version): ?array
{
    if (1 !== preg_match('/^v?(\d+)\.(\d+)/', $version, $m)) {
        return null;
    }

    return [(int) $m[1], (int) $m[2]];
}

/**
 * @param array{int, int} $a
 * @param array{int, int} $b
 */
function tupleLessOrEqual(array $a, array $b): bool
{
    if ($a[0] !== $b[0]) {
        return $a[0] < $b[0];
    }

    return $a[1] <= $b[1];
}

/**
 * Reads the "Bug fix?" / "New feature?" answers from the PR body table.
 *
 * @return 'yes'|'no'|'unknown'
 */
function frontmatterAnswer(string $body, string $label): string
{
    foreach (explode("\n", $body) as $line) {
        if (false === stripos($line, $label)) {
            continue;
        }

        $cells = array_map('trim', explode('|', $line));
        // Drop the leading empty cell and the question cell, look at the answer cell.
        $answer = $cells[2] ?? '';
        $answer = preg_replace('/<!--.*?-->/s', '', $answer);
        $answer = strtolower(trim((string) $answer));

        $hasYes = false !== strpos($answer, 'yes');
        $hasNo = false !== strpos($answer, 'no');

        if ($hasYes && !$hasNo) {
            return 'yes';
        }
        if ($hasNo && !$hasYes) {
            return 'no';
        }

        return 'unknown';
    }

    return 'unknown';
}

function annotate(string $level, string $message, ?string $file = null): void
{
    if (null !== $file) {
        fwrite(\STDOUT, sprintf("::%s file=%s::%s\n", $level, $file, $message));

        return;
    }

    fwrite(\STDOUT, sprintf("::%s::%s\n", $level, $message));
}

// --- Gather inputs ----------------------------------------------------------

$body = getenv('PR_BODY') ?: '';
$labelsJson = getenv('PR_LABELS') ?: '[]';
$baseSha = getenv('BASE_SHA') ?: '';
$headSha = getenv('HEAD_SHA') ?: 'HEAD';

$labels = json_decode($labelsJson, true);
if (!\is_array($labels)) {
    $labels = [];
}
$hasBcBreakLabel = false;
foreach ($labels as $name) {
    if (0 === strcasecmp((string) $name, BC_BREAK_LABEL)) {
        $hasBcBreakLabel = true;
        break;
    }
}

$bugFlag = frontmatterAnswer($body, 'Bug fix?');
$featureFlag = frontmatterAnswer($body, 'New feature?');

// Latest released minor version from tags (none today => nothing frozen).
$tags = git('git tag --sort=-v:refname');
$latestMinor = null;
foreach ($tags as $tag) {
    $tuple = minorTuple($tag);
    if (null !== $tuple) {
        $latestMinor = $tuple;
        break;
    }
}

// Changed files limited to the PR's own diff.
// Two-dot diff against the checked-out HEAD. On a pull_request event HEAD is the
// GitHub merge commit (base + head), so diffing the base commit against it yields
// exactly the PR's changes and stays correct even when the branch is behind base.
$diffRange = '' !== $baseSha ? escapeshellarg($baseSha).' '.escapeshellarg($headSha) : escapeshellarg($headSha);
$changedFiles = git('git diff --name-only '.$diffRange);
$deletedFiles = git('git diff --name-only --diff-filter=D '.$diffRange);
$addedFiles = git('git diff --name-only --diff-filter=A '.$diffRange);

// A bridge moving to another component deletes its CHANGELOG.md and adds it again, keyed by bridge name.
$addedBridgeChangelogs = [];
foreach ($addedFiles as $file) {
    if (1 === preg_match('#^src/[^/]+/src/Bridge/([^/]+)/CHANGELOG\.md$#', $file, $m)) {
        $addedBridgeChangelogs[$m[1]] = true;
    }
}
$movedChangelogs = [];
foreach ($deletedFiles as $file) {
    if (1 === preg_match('#^src/[^/]+/src/Bridge/([^/]+)/CHANGELOG\.md$#', $file, $m) && isset($addedBridgeChangelogs[$m[1]])) {
        $movedChangelogs[$m[1]] = $file;
    }
}

// --- Analyse CHANGELOG.md / UPGRADE.md changes ------------------------------

$errors = [];
$touchedComponents = [];
$touchedBridges = [];
$changelogFilesWithUpcomingEntry = [];
$anyMdChanged = false;
$upgradeUpcomingChanged = false;

foreach ($changedFiles as $file) {
    if (1 === preg_match('#^src/([^/]+)/src/Bridge/([^/]+)/#', $file, $m)) {
        $touchedComponents[$m[1]] = true;
        $touchedBridges[$m[1].'/'.$m[2]] = true;
    } elseif (1 === preg_match('#^src/([^/]+)/#', $file, $m)) {
        $touchedComponents[$m[1]] = true;
    }
}

foreach ($changedFiles as $file) {
    $isChangelog = (bool) preg_match('#(^|/)CHANGELOG\.md$#', $file);
    $isUpgrade = 'UPGRADE.md' === $file;
    if (!$isChangelog && !$isUpgrade) {
        continue;
    }

    $anyMdChanged = true;
    $kind = $isChangelog ? 'changelog' : 'upgrade';

    if (\in_array($file, $movedChangelogs, true)) {
        continue;
    }

    $basePath = $file;
    if (1 === preg_match('#^src/[^/]+/src/Bridge/([^/]+)/CHANGELOG\.md$#', $file, $m) && isset($movedChangelogs[$m[1]])) {
        $basePath = $movedChangelogs[$m[1]];
    }

    $baseSections = parseSections(fileAtRevision('' !== $baseSha ? $baseSha : 'HEAD~1', $basePath), $kind);
    $headSections = parseSections(fileAtRevision($headSha, $file), $kind);

    $versions = array_unique(array_merge(array_keys($baseSections), array_keys($headSections)));
    foreach ($versions as $version) {
        $baseLines = $baseSections[$version] ?? [];
        $headLines = $headSections[$version] ?? [];
        if ($baseLines === $headLines) {
            continue;
        }

        $tuple = minorTuple($version);
        $frozen = null !== $latestMinor && null !== $tuple && tupleLessOrEqual($tuple, $latestMinor);

        if ($frozen) {
            $errors[] = [
                'message' => sprintf('Section "%s" is already released (latest tag minor %d.%d); add your entry to the upcoming version section instead.', $version, $latestMinor[0], $latestMinor[1]),
                'file' => $file,
            ];
            continue;
        }

        // Upcoming (unreleased) section changed.
        if ($isChangelog && [] !== addedBullets($baseLines, $headLines)) {
            $changelogFilesWithUpcomingEntry[$file] = true;
        }
        if ($isUpgrade && [] !== addedBullets($baseLines, $headLines)) {
            $upgradeUpcomingChanged = true;
        }
    }
}

// --- Apply rules ------------------------------------------------------------

// Rule 2: bug-only PRs must not touch CHANGELOG/UPGRADE (unless it is a BC break).
if ('yes' === $bugFlag && 'no' === $featureFlag && !$hasBcBreakLabel && $anyMdChanged) {
    $errors[] = ['message' => 'This PR is marked as a bug fix only, but it modifies CHANGELOG.md/UPGRADE.md. Bug fixes should not contain changelog or upgrade entries.', 'file' => null];
}

// Rule 1: feature PRs must add a changelog entry for a touched component/bridge.
if ('yes' === $featureFlag) {
    $expectedChangelogs = [];
    foreach (array_keys($touchedBridges) as $bridge) {
        [$component, $bridgeName] = explode('/', $bridge);
        $expectedChangelogs['src/'.$component.'/src/Bridge/'.$bridgeName.'/CHANGELOG.md'] = true;
        $expectedChangelogs['src/'.$component.'/CHANGELOG.md'] = true;
    }
    foreach (array_keys($touchedComponents) as $component) {
        $expectedChangelogs['src/'.$component.'/CHANGELOG.md'] = true;
    }

    if ([] === $expectedChangelogs) {
        // Could not map a component (e.g. no src/ change) => require an entry anywhere.
        if ([] === $changelogFilesWithUpcomingEntry) {
            $errors[] = ['message' => 'This PR is marked as a new feature but no CHANGELOG.md entry was added.', 'file' => null];
        }
    } else {
        $satisfied = false;
        foreach (array_keys($changelogFilesWithUpcomingEntry) as $file) {
            if (isset($expectedChangelogs[$file])) {
                $satisfied = true;
                break;
            }
        }
        if (!$satisfied) {
            $errors[] = ['message' => sprintf('This PR is marked as a new feature but no CHANGELOG.md entry was added for the changed component(s): %s. Add an entry to one of: %s.', implode(', ', array_keys($touchedComponents)), implode(', ', array_keys($expectedChangelogs))), 'file' => null];
        }
    }
}

// Rule 3: BC Break label requires an UPGRADE.md entry.
if ($hasBcBreakLabel && !$upgradeUpcomingChanged) {
    $errors[] = ['message' => sprintf('This PR carries the "%s" label but UPGRADE.md has no new entry in the upcoming version section.', BC_BREAK_LABEL), 'file' => 'UPGRADE.md'];
}

// Rule 4: UPGRADE.md changes require the BC Break label.
if ($upgradeUpcomingChanged && !$hasBcBreakLabel) {
    $errors[] = ['message' => sprintf('UPGRADE.md was modified but the PR is missing the "%s" label.', BC_BREAK_LABEL), 'file' => 'UPGRADE.md'];
}

// Frontmatter sanity: warn (do not fail) when the template table is unfilled.
if ('unknown' === $bugFlag && 'unknown' === $featureFlag) {
    annotate('warning', 'Could not determine "Bug fix?"/"New feature?" from the PR description; some changelog checks were skipped. Please fill in the PR template table.');
}

// --- Report -----------------------------------------------------------------

if ([] === $errors) {
    fwrite(\STDOUT, "\xe2\x9c\x93 CHANGELOG.md/UPGRADE.md entries are consistent with the PR metadata.\n");
    exit(0);
}

foreach ($errors as $error) {
    annotate('error', $error['message'], $error['file']);
    fwrite(\STDOUT, sprintf("\xe2\x9c\x97 %s%s\n", null !== $error['file'] ? $error['file'].': ' : '', $error['message']));
}

fwrite(\STDOUT, sprintf("\n%d changelog/upgrade validation error(s) found.\n", \count($errors)));

exit(1);
