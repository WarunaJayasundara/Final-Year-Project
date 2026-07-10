import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Bot, MessageCircle, Send, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useCurrentUser } from '@/features/auth/useAuth';
import { useSendChatMessage } from './useCoach';
import type { ChatMessage } from './types';

export function ChatWidget() {
  const { t } = useTranslation('coach');
  const { data: user } = useCurrentUser();
  const [open, setOpen] = useState(false);
  const [draft, setDraft] = useState('');
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const scrollRef = useRef<HTMLDivElement>(null);

  const sendMessage = useSendChatMessage({
    onSuccess: (reply) => {
      setMessages((prev) => [...prev, { role: 'assistant', content: reply }]);
    },
    onError: () => {
      setErrorMessage(t('error'));
    },
  });

  useEffect(() => {
    scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: 'smooth' });
  }, [messages, sendMessage.isPending]);

  if (!user || user.role !== 'user') {
    return null;
  }

  const handleSend = () => {
    const text = draft.trim();
    if (!text || sendMessage.isPending) return;

    setErrorMessage(null);
    const history = messages;
    setMessages((prev) => [...prev, { role: 'user', content: text }]);
    setDraft('');
    sendMessage.mutate({ message: text, history });
  };

  return (
    <div className="fixed bottom-4 right-4 z-50 flex flex-col items-end gap-3">
      {open && (
        <Card className="flex h-[28rem] w-80 flex-col overflow-hidden shadow-2xl sm:w-96">
          <CardHeader className="flex-row items-center justify-between gap-2 border-b bg-primary/5 [&]:pb-3">
            <CardTitle className="flex items-center gap-2 text-sm">
              <Bot className="h-4 w-4 text-primary" /> {t('title')}
            </CardTitle>
            <Button size="icon-sm" variant="ghost" aria-label={t('closeLabel')} onClick={() => setOpen(false)}>
              <X className="h-4 w-4" />
            </Button>
          </CardHeader>

          <div ref={scrollRef} className="flex-1 overflow-y-auto p-4">
            <div className="flex flex-col gap-3">
              <div className="max-w-[85%] self-start rounded-xl bg-muted px-3 py-2 text-sm">{t('greeting')}</div>
              {messages.map((message, index) => (
                <div
                  key={index}
                  className={`max-w-[85%] rounded-xl px-3 py-2 text-sm ${
                    message.role === 'user'
                      ? 'self-end bg-primary text-primary-foreground'
                      : 'self-start bg-muted'
                  }`}
                >
                  {message.content}
                </div>
              ))}
              {sendMessage.isPending && (
                <div className="self-start rounded-xl bg-muted px-3 py-2 text-sm text-muted-foreground">…</div>
              )}
              {errorMessage && (
                <div className="self-start rounded-xl bg-destructive/10 px-3 py-2 text-sm text-destructive">
                  {errorMessage}
                </div>
              )}
            </div>
          </div>

          <CardContent className="flex gap-2 border-t p-3">
            <Input
              value={draft}
              onChange={(e) => setDraft(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  e.preventDefault();
                  handleSend();
                }
              }}
              placeholder={t('placeholder')}
              disabled={sendMessage.isPending}
            />
            <Button size="icon" onClick={handleSend} disabled={sendMessage.isPending || !draft.trim()}>
              <Send className="h-4 w-4" />
            </Button>
          </CardContent>
        </Card>
      )}

      <Button
        size="icon-lg"
        className="h-14 w-14 rounded-full shadow-xl"
        aria-label={t('openLabel')}
        onClick={() => setOpen((prev) => !prev)}
      >
        {open ? <X className="h-6 w-6" /> : <MessageCircle className="h-6 w-6" />}
      </Button>
    </div>
  );
}
