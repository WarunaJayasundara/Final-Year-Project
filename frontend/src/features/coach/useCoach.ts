import { useMutation } from '@tanstack/react-query';
import { sendChatMessage } from './api';
import type { ChatMessage } from './types';

// onSuccess/onError are wired as hook-level useMutation options (not passed to
// .mutate() at the call site) - per-call mutate() callbacks can be dropped if the
// observer has no active subscriber at settle time; hook-level callbacks always fire.
export function useSendChatMessage(options?: { onSuccess?: (reply: string) => void; onError?: (error: unknown) => void }) {
  return useMutation({
    mutationFn: ({ message, history }: { message: string; history: ChatMessage[] }) => sendChatMessage(message, history),
    ...options,
  });
}
