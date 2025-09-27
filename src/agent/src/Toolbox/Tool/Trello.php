<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Tool;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
#[AsTool('trello_get_cards', 'Tool that gets Trello cards')]
#[AsTool('trello_create_card', 'Tool that creates Trello cards', method: 'createCard')]
#[AsTool('trello_update_card', 'Tool that updates Trello cards', method: 'updateCard')]
#[AsTool('trello_get_lists', 'Tool that gets Trello lists', method: 'getLists')]
#[AsTool('trello_get_boards', 'Tool that gets Trello boards', method: 'getBoards')]
#[AsTool('trello_get_members', 'Tool that gets Trello members', method: 'getMembers')]
final readonly class Trello
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
        #[\SensitiveParameter] private string $apiToken,
        private string $apiVersion = '1',
        private array $options = [],
    ) {
    }

    /**
     * Get Trello cards.
     *
     * @param string $boardId Board ID to filter cards
     * @param string $listId  List ID to filter cards
     * @param string $member  Member ID to filter cards
     * @param string $label   Label to filter cards
     * @param bool   $open    Include only open cards
     * @param bool   $closed  Include only closed cards
     * @param int    $limit   Number of cards to retrieve
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     desc: string,
     *     closed: bool,
     *     idList: string,
     *     idBoard: string,
     *     pos: float,
     *     due: string|null,
     *     dueComplete: bool,
     *     dateLastActivity: string,
     *     idLabels: array<int, string>,
     *     idMembers: array<int, string>,
     *     idChecklists: array<int, string>,
     *     idMembersVoted: array<int, string>,
     *     idAttachmentCover: string|null,
     *     manualCoverAttachment: bool,
     *     url: string,
     *     shortUrl: string,
     *     labels: array<int, array{id: string, name: string, color: string}>,
     *     members: array<int, array{id: string, username: string, fullName: string}>,
     *     checklists: array<int, array{id: string, name: string}>,
     *     attachments: array<int, array{id: string, name: string, url: string}>,
     *     badges: array{
     *         votes: int,
     *         viewingMemberVoted: bool,
     *         subscribed: bool,
     *         fogbugz: string,
     *         checkItems: int,
     *         checkItemsChecked: int,
     *         comments: int,
     *         attachments: int,
     *         description: bool,
     *         due: string|null,
     *         dueComplete: bool,
     *     },
     * }>
     */
    public function __invoke(
        string $boardId = '',
        string $listId = '',
        string $member = '',
        string $label = '',
        bool $open = true,
        bool $closed = false,
        int $limit = 100,
    ): array {
        try {
            $params = [
                'key' => $this->apiKey,
                'token' => $this->apiToken,
                'limit' => min(max($limit, 1), 1000),
                'fields' => 'id,name,desc,closed,idList,idBoard,pos,due,dueComplete,dateLastActivity,idLabels,idMembers,idChecklists,idMembersVoted,idAttachmentCover,manualCoverAttachment,url,shortUrl,labels,members,checklists,attachments,badges',
            ];

            if ($boardId) {
                $params['idBoard'] = $boardId;
            }
            if ($listId) {
                $params['idList'] = $listId;
            }
            if ($member) {
                $params['idMembers'] = $member;
            }
            if ($label) {
                $params['idLabels'] = $label;
            }

            $response = $this->httpClient->request('GET', "https://api.trello.com/{$this->apiVersion}/search", [
                'query' => array_merge($params, [
                    'query' => 'is:open',
                    'modelTypes' => 'cards',
                ], $this->options),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            $cards = $data['cards'] ?? [];

            // Filter by open/closed status
            $filteredCards = array_filter($cards, fn ($card) => ($open && !$card['closed']) || ($closed && $card['closed'])
            );

            return array_map(fn ($card) => [
                'id' => $card['id'],
                'name' => $card['name'],
                'desc' => $card['desc'] ?? '',
                'closed' => $card['closed'],
                'idList' => $card['idList'],
                'idBoard' => $card['idBoard'],
                'pos' => $card['pos'],
                'due' => $card['due'],
                'dueComplete' => $card['dueComplete'] ?? false,
                'dateLastActivity' => $card['dateLastActivity'],
                'idLabels' => $card['idLabels'] ?? [],
                'idMembers' => $card['idMembers'] ?? [],
                'idChecklists' => $card['idChecklists'] ?? [],
                'idMembersVoted' => $card['idMembersVoted'] ?? [],
                'idAttachmentCover' => $card['idAttachmentCover'],
                'manualCoverAttachment' => $card['manualCoverAttachment'] ?? false,
                'url' => $card['url'],
                'shortUrl' => $card['shortUrl'],
                'labels' => array_map(fn ($label) => [
                    'id' => $label['id'],
                    'name' => $label['name'],
                    'color' => $label['color'],
                ], $card['labels'] ?? []),
                'members' => array_map(fn ($member) => [
                    'id' => $member['id'],
                    'username' => $member['username'],
                    'fullName' => $member['fullName'],
                ], $card['members'] ?? []),
                'checklists' => array_map(fn ($checklist) => [
                    'id' => $checklist['id'],
                    'name' => $checklist['name'],
                ], $card['checklists'] ?? []),
                'attachments' => array_map(fn ($attachment) => [
                    'id' => $attachment['id'],
                    'name' => $attachment['name'],
                    'url' => $attachment['url'],
                ], $card['attachments'] ?? []),
                'badges' => [
                    'votes' => $card['badges']['votes'] ?? 0,
                    'viewingMemberVoted' => $card['badges']['viewingMemberVoted'] ?? false,
                    'subscribed' => $card['badges']['subscribed'] ?? false,
                    'fogbugz' => $card['badges']['fogbugz'] ?? '',
                    'checkItems' => $card['badges']['checkItems'] ?? 0,
                    'checkItemsChecked' => $card['badges']['checkItemsChecked'] ?? 0,
                    'comments' => $card['badges']['comments'] ?? 0,
                    'attachments' => $card['badges']['attachments'] ?? 0,
                    'description' => $card['badges']['description'] ?? false,
                    'due' => $card['badges']['due'],
                    'dueComplete' => $card['badges']['dueComplete'] ?? false,
                ],
            ], $filteredCards);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create a Trello card.
     *
     * @param string             $name       Card name
     * @param string             $desc       Card description
     * @param string             $idList     List ID
     * @param string             $due        Due date
     * @param array<int, string> $idMembers  Member IDs
     * @param array<int, string> $idLabels   Label IDs
     * @param float              $pos        Position
     * @param bool               $subscribed Subscribe to card
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     desc: string,
     *     closed: bool,
     *     idList: string,
     *     idBoard: string,
     *     pos: float,
     *     due: string|null,
     *     dueComplete: bool,
     *     dateLastActivity: string,
     *     idLabels: array<int, string>,
     *     idMembers: array<int, string>,
     *     url: string,
     *     shortUrl: string,
     *     badges: array<string, mixed>,
     * }|string
     */
    public function createCard(
        string $name,
        string $desc = '',
        string $idList = '',
        string $due = '',
        array $idMembers = [],
        array $idLabels = [],
        float $pos = 0,
        bool $subscribed = false,
    ): array|string {
        try {
            $payload = [
                'key' => $this->apiKey,
                'token' => $this->apiToken,
                'name' => $name,
            ];

            if ($desc) {
                $payload['desc'] = $desc;
            }
            if ($idList) {
                $payload['idList'] = $idList;
            }
            if ($due) {
                $payload['due'] = $due;
            }
            if (!empty($idMembers)) {
                $payload['idMembers'] = implode(',', $idMembers);
            }
            if (!empty($idLabels)) {
                $payload['idLabels'] = implode(',', $idLabels);
            }
            if ($pos > 0) {
                $payload['pos'] = $pos;
            }
            $payload['subscribed'] = $subscribed;

            $response = $this->httpClient->request('POST', "https://api.trello.com/{$this->apiVersion}/cards", [
                'query' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error creating card: '.($data['error'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'desc' => $data['desc'] ?? '',
                'closed' => $data['closed'],
                'idList' => $data['idList'],
                'idBoard' => $data['idBoard'],
                'pos' => $data['pos'],
                'due' => $data['due'],
                'dueComplete' => $data['dueComplete'] ?? false,
                'dateLastActivity' => $data['dateLastActivity'],
                'idLabels' => $data['idLabels'] ?? [],
                'idMembers' => $data['idMembers'] ?? [],
                'url' => $data['url'],
                'shortUrl' => $data['shortUrl'],
                'badges' => $data['badges'] ?? [],
            ];
        } catch (\Exception $e) {
            return 'Error creating card: '.$e->getMessage();
        }
    }

    /**
     * Update a Trello card.
     *
     * @param string             $cardId    Card ID to update
     * @param string             $name      New name (optional)
     * @param string             $desc      New description (optional)
     * @param string             $due       New due date (optional)
     * @param bool               $closed    New closed status (optional)
     * @param array<int, string> $idMembers New member IDs (optional)
     * @param array<int, string> $idLabels  New label IDs (optional)
     */
    public function updateCard(
        string $cardId,
        string $name = '',
        string $desc = '',
        string $due = '',
        bool $closed = false,
        array $idMembers = [],
        array $idLabels = [],
    ): string {
        try {
            $payload = [
                'key' => $this->apiKey,
                'token' => $this->apiToken,
            ];

            if ($name) {
                $payload['name'] = $name;
            }
            if ($desc) {
                $payload['desc'] = $desc;
            }
            if ($due) {
                $payload['due'] = $due;
            }
            $payload['closed'] = $closed;
            if (!empty($idMembers)) {
                $payload['idMembers'] = implode(',', $idMembers);
            }
            if (!empty($idLabels)) {
                $payload['idLabels'] = implode(',', $idLabels);
            }

            $response = $this->httpClient->request('PUT', "https://api.trello.com/{$this->apiVersion}/cards/{$cardId}", [
                'query' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error updating card: '.($data['error'] ?? 'Unknown error');
            }

            return 'Card updated successfully';
        } catch (\Exception $e) {
            return 'Error updating card: '.$e->getMessage();
        }
    }

    /**
     * Get Trello lists.
     *
     * @param string $boardId Board ID to filter lists
     * @param string $filter  Filter (all, closed, none, open)
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     closed: bool,
     *     idBoard: string,
     *     pos: float,
     *     subscribed: bool,
     * }>
     */
    public function getLists(
        string $boardId = '',
        string $filter = 'open',
    ): array {
        try {
            $params = [
                'key' => $this->apiKey,
                'token' => $this->apiToken,
                'filter' => $filter,
                'fields' => 'id,name,closed,idBoard,pos,subscribed',
            ];

            $url = $boardId
                ? "https://api.trello.com/{$this->apiVersion}/boards/{$boardId}/lists"
                : "https://api.trello.com/{$this->apiVersion}/members/me/lists";

            $response = $this->httpClient->request('GET', $url, [
                'query' => array_merge($params, $this->options),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($list) => [
                'id' => $list['id'],
                'name' => $list['name'],
                'closed' => $list['closed'],
                'idBoard' => $list['idBoard'],
                'pos' => $list['pos'],
                'subscribed' => $list['subscribed'] ?? false,
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Trello boards.
     *
     * @param string $filter Filter (all, closed, members, open, organization, pinned, public, starred, templates)
     * @param string $fields Fields to return
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     desc: string,
     *     closed: bool,
     *     idOrganization: string|null,
     *     pinned: bool,
     *     url: string,
     *     shortUrl: string,
     *     prefs: array{
     *         permissionLevel: string,
     *         hideVotes: bool,
     *         voting: string,
     *         comments: string,
     *         invitations: string,
     *         selfJoin: bool,
     *         cardCovers: bool,
     *         isTemplate: bool,
     *         cardAging: string,
     *         calendarFeedEnabled: bool,
     *         background: string,
     *         backgroundImage: string|null,
     *         backgroundImageScaled: array<int, array{width: int, height: int, url: string}>|null,
     *         backgroundTile: bool,
     *         backgroundBrightness: string,
     *         backgroundColor: string,
     *         backgroundBottomColor: string,
     *         backgroundTopColor: string,
     *         canBePublic: bool,
     *         canBeOrg: bool,
     *         canBePrivate: bool,
     *         canInvite: bool,
     *     },
     *     labelNames: array{
     *         green: string,
     *         yellow: string,
     *         orange: string,
     *         red: string,
     *         purple: string,
     *         blue: string,
     *         sky: string,
     *         lime: string,
     *         pink: string,
     *         black: string,
     *     },
     *     dateLastActivity: string,
     *     dateLastView: string,
     * }>
     */
    public function getBoards(
        string $filter = 'open',
        string $fields = 'id,name,desc,closed,idOrganization,pinned,url,shortUrl,prefs,labelNames,dateLastActivity,dateLastView',
    ): array {
        try {
            $params = [
                'key' => $this->apiKey,
                'token' => $this->apiToken,
                'filter' => $filter,
                'fields' => $fields,
            ];

            $response = $this->httpClient->request('GET', "https://api.trello.com/{$this->apiVersion}/members/me/boards", [
                'query' => array_merge($params, $this->options),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($board) => [
                'id' => $board['id'],
                'name' => $board['name'],
                'desc' => $board['desc'] ?? '',
                'closed' => $board['closed'],
                'idOrganization' => $board['idOrganization'],
                'pinned' => $board['pinned'],
                'url' => $board['url'],
                'shortUrl' => $board['shortUrl'],
                'prefs' => $board['prefs'],
                'labelNames' => $board['labelNames'],
                'dateLastActivity' => $board['dateLastActivity'],
                'dateLastView' => $board['dateLastView'],
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Trello members.
     *
     * @param string $boardId Board ID to filter members
     * @param string $fields  Fields to return
     *
     * @return array<int, array{
     *     id: string,
     *     username: string,
     *     fullName: string,
     *     initials: string,
     *     avatarHash: string|null,
     *     avatarUrl: string|null,
     *     email: string,
     *     idBoards: array<int, string>,
     *     idOrganizations: array<int, string>,
     *     loginTypes: array<int, string>,
     *     newEmail: string|null,
     *     status: string,
     * }>
     */
    public function getMembers(
        string $boardId = '',
        string $fields = 'id,username,fullName,initials,avatarHash,avatarUrl,email,idBoards,idOrganizations,loginTypes,newEmail,status',
    ): array {
        try {
            $params = [
                'key' => $this->apiKey,
                'token' => $this->apiToken,
                'fields' => $fields,
            ];

            $url = $boardId
                ? "https://api.trello.com/{$this->apiVersion}/boards/{$boardId}/members"
                : "https://api.trello.com/{$this->apiVersion}/members/me";

            $response = $this->httpClient->request('GET', $url, [
                'query' => array_merge($params, $this->options),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            $members = \is_array($data) && isset($data[0]) ? $data : [$data];

            return array_map(fn ($member) => [
                'id' => $member['id'],
                'username' => $member['username'],
                'fullName' => $member['fullName'],
                'initials' => $member['initials'],
                'avatarHash' => $member['avatarHash'],
                'avatarUrl' => $member['avatarUrl'],
                'email' => $member['email'] ?? '',
                'idBoards' => $member['idBoards'] ?? [],
                'idOrganizations' => $member['idOrganizations'] ?? [],
                'loginTypes' => $member['loginTypes'] ?? [],
                'newEmail' => $member['newEmail'],
                'status' => $member['status'],
            ], $members);
        } catch (\Exception $e) {
            return [];
        }
    }
}
