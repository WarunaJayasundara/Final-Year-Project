import { api } from '@/lib/api';
import type { ChatMessage } from './types';

export async function sendChatMessage(message: string, history: ChatMessage[]): Promise<string> {
  const { data } = await api.post<{ data: { reply: string } }>('/coach/chat', { message, history });
  return data.data.reply;
}
