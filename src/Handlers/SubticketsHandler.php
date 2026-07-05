<?php
/**
 * Parent/child ticket relationships.
 *
 * Implemented directly on osTicket's native `ost_ticket.ticket_pid` column, so
 * it needs no third-party subticket plugin. A ticket has at most one parent.
 */

declare(strict_types=1);

namespace NksOsticket;

final class SubticketsHandler
{
    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function link(array $params): array
    {
        $parent = $this->lookup($params['parent'] ?? null, 'parent');
        $child = $this->lookup($params['child'] ?? null, 'child');

        if ($parent->getId() === $child->getId()) {
            throw new ApiException('A ticket cannot be its own parent', 400);
        }
        $existingPid = (int)($child->ticket_pid ?? 0);
        if ($existingPid > 0 && $existingPid !== $parent->getId()) {
            throw new ApiException('Child ticket already has a different parent', 409);
        }

        $this->setParent($child->getId(), $parent->getId());

        return [
            'parent' => ['number' => $parent->getNumber(), 'id' => (int)$parent->getId()],
            'child' => ['number' => $child->getNumber(), 'id' => (int)$child->getId()],
            'linked' => true,
        ];
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function unlink(array $params): array
    {
        $child = $this->lookup($params['child'] ?? $params['number'] ?? null, 'child');
        $this->setParent($child->getId(), 0);
        return ['child' => ['number' => $child->getNumber(), 'id' => (int)$child->getId()], 'unlinked' => true];
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function children(array $params): array
    {
        $parent = $this->lookup($params['number'] ?? $params['parent'] ?? null, 'parent');
        $table = defined('TICKET_TABLE') ? TICKET_TABLE : 'ost_ticket';
        $sql = sprintf('SELECT ticket_id FROM %s WHERE ticket_pid = %d ORDER BY ticket_id', $table, $parent->getId());

        $children = [];
        if ($res = \db_query($sql)) {
            while ($row = \db_fetch_array($res)) {
                if ($c = \Ticket::lookup((int)$row['ticket_id'])) {
                    $children[] = $this->brief($c);
                }
            }
        }
        return ['parent' => $parent->getNumber(), 'children' => $children, 'count' => count($children)];
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function parent(array $params): array
    {
        $child = $this->lookup($params['number'] ?? $params['child'] ?? null, 'child');
        $pid = (int)($child->ticket_pid ?? 0);
        if ($pid <= 0) {
            return ['child' => $child->getNumber(), 'parent' => null];
        }
        $parent = \Ticket::lookup($pid);
        return ['child' => $child->getNumber(), 'parent' => $parent ? $this->brief($parent) : null];
    }

    // --------------------------------------------------------------- helpers

    private function setParent(int $childId, int $parentId): void
    {
        $table = defined('TICKET_TABLE') ? TICKET_TABLE : 'ost_ticket';
        $sql = sprintf('UPDATE %s SET ticket_pid = %d WHERE ticket_id = %d', $table, $parentId, $childId);
        if (!\db_query($sql)) {
            throw new ApiException('Failed to update parent relationship', 500);
        }
    }

    private function lookup($identifier, string $label): \Ticket
    {
        if ($identifier === null || $identifier === '') {
            throw new ApiException(sprintf('Missing "%s" ticket number', $label), 400);
        }
        $ticket = \Ticket::lookupByNumber((string)$identifier);
        if (!$ticket && is_numeric($identifier)) {
            $ticket = \Ticket::lookup((int)$identifier);
        }
        if (!$ticket) {
            throw new ApiException(sprintf('%s ticket not found: %s', ucfirst($label), $identifier), 404);
        }
        return $ticket;
    }

    /**
     * @return array<string,mixed>
     */
    private function brief(\Ticket $ticket): array
    {
        return [
            'id' => (int)$ticket->getId(),
            'number' => $ticket->getNumber(),
            'subject' => $ticket->getSubject(),
            'status' => (string)$ticket->getStatus(),
        ];
    }
}
