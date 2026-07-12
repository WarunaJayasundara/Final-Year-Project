import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { BookOpen, Check, FileSearch, FileText, Trash2, UploadCloud, X } from 'lucide-react';
import { InlineLoader } from '@/components/brand/BrandLoader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
  useAdminStudyNotes,
  useAnalyzeSourceDocument,
  useDeleteSourceDocument,
  useGenerateStudyNote,
  usePublishStudyNote,
  useRejectStudyNote,
  useSourceDocuments,
  useUploadSourceDocument,
} from '@/features/admin/useAdmin';
import type { SourceDocument, SourceDocumentStatus, SourceDocumentType, StudyNote } from '@/features/admin/types';

const DOCUMENT_TYPES: SourceDocumentType[] = ['past_paper', 'iq_book', 'exam_guide', 'theory_book', 'other'];

const STATUS_VARIANT: Record<SourceDocumentStatus, 'secondary' | 'default' | 'outline' | 'destructive'> = {
  pending: 'secondary',
  analyzing: 'default',
  analyzed: 'outline',
  failed: 'destructive',
};

export function AdminKnowledgeLibraryPage() {
  const { t } = useTranslation('admin');
  const { data: documents, isLoading } = useSourceDocuments();
  const { data: notes, isLoading: notesLoading } = useAdminStudyNotes('draft');

  const fileInputRef = useRef<HTMLInputElement>(null);
  const [file, setFile] = useState<File | null>(null);
  const [title, setTitle] = useState('');
  const [documentType, setDocumentType] = useState<SourceDocumentType>('past_paper');
  const [year, setYear] = useState('');

  const upload = useUploadSourceDocument();
  const analyze = useAnalyzeSourceDocument();
  const remove = useDeleteSourceDocument();
  const generateNote = useGenerateStudyNote();
  const publishNote = usePublishStudyNote();
  const rejectNote = useRejectStudyNote();

  const handleUpload = () => {
    if (!file || !title) return;
    upload.mutate(
      { file, title, document_type: documentType, year: year || undefined },
      {
        onSuccess: () => {
          toast.success(t('knowledgeLibrary.uploadSuccess'));
          setFile(null);
          setTitle('');
          setYear('');
          if (fileInputRef.current) fileInputRef.current.value = '';
        },
        onError: () => toast.error(t('knowledgeLibrary.uploadError')),
      },
    );
  };

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t('knowledgeLibrary.title')}</h1>
        <p className="text-muted-foreground">{t('knowledgeLibrary.subtitle')}</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <UploadCloud className="h-4 w-4 text-primary" /> {t('knowledgeLibrary.uploadTitle')}
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div className="flex flex-col gap-1.5 lg:col-span-2">
              <Label htmlFor="doc-title">{t('knowledgeLibrary.documentTitle')}</Label>
              <Input id="doc-title" value={title} onChange={(e) => setTitle(e.target.value)} />
            </div>

            <div className="flex flex-col gap-1.5">
              <Label>{t('knowledgeLibrary.documentType')}</Label>
              <Select value={documentType} onValueChange={(v) => setDocumentType(v as SourceDocumentType)}>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {DOCUMENT_TYPES.map((type) => (
                    <SelectItem key={type} value={type}>
                      {t(`knowledgeLibrary.documentType${toPascalCase(type)}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="flex flex-col gap-1.5">
              <Label htmlFor="doc-year">{t('knowledgeLibrary.year')}</Label>
              <Input id="doc-year" value={year} onChange={(e) => setYear(e.target.value)} placeholder="2019" />
            </div>

            <div className="flex flex-col gap-1.5 lg:col-span-4">
              <Input
                ref={fileInputRef}
                type="file"
                accept="application/pdf"
                onChange={(e) => setFile(e.target.files?.[0] ?? null)}
              />
            </div>
          </div>

          <Button className="mt-4" onClick={handleUpload} disabled={!file || !title || upload.isPending}>
            {upload.isPending ? <InlineLoader /> : t('knowledgeLibrary.upload')}
          </Button>
        </CardContent>
      </Card>

      <div className="flex flex-col gap-4">
        {isLoading && <p className="text-sm text-muted-foreground">-</p>}
        {!isLoading && documents?.data.length === 0 && (
          <p className="text-sm text-muted-foreground">{t('knowledgeLibrary.noDocuments')}</p>
        )}
        {documents?.data.map((doc) => (
          <DocumentCard
            key={doc.id}
            doc={doc}
            onAnalyze={() => analyze.mutate(doc.id, { onSuccess: () => toast.success(t('knowledgeLibrary.analyzeSuccess')), onError: () => toast.error(t('knowledgeLibrary.analyzeError')) })}
            onDelete={() => remove.mutate(doc.id)}
            onGenerateNote={() =>
              generateNote.mutate(
                { source_document_id: doc.id },
                {
                  onSuccess: () => toast.success(t('knowledgeLibrary.generateNoteSuccess')),
                  onError: () => toast.error(t('knowledgeLibrary.generateNoteError')),
                },
              )
            }
            analyzing={analyze.isPending}
            generatingNote={generateNote.isPending}
          />
        ))}
      </div>

      <div className="flex flex-col gap-4">
        <h2 className="text-lg font-semibold">{t('knowledgeLibrary.draftNotesTitle')}</h2>
        {notesLoading && <p className="text-sm text-muted-foreground">-</p>}
        {!notesLoading && notes?.data.length === 0 && (
          <p className="text-sm text-muted-foreground">{t('knowledgeLibrary.noDraftNotes')}</p>
        )}
        {notes?.data.map((note) => (
          <StudyNoteCard
            key={note.id}
            note={note}
            onPublish={() => publishNote.mutate(note.id, { onSuccess: () => toast.success(t('knowledgeLibrary.publishSuccess')) })}
            onReject={() => rejectNote.mutate(note.id)}
            busy={publishNote.isPending || rejectNote.isPending}
          />
        ))}
      </div>
    </div>
  );
}

function toPascalCase(type: string): string {
  return type
    .split('_')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join('');
}

function DocumentCard({
  doc,
  onAnalyze,
  onDelete,
  onGenerateNote,
  analyzing,
  generatingNote,
}: {
  doc: SourceDocument;
  onAnalyze: () => void;
  onDelete: () => void;
  onGenerateNote: () => void;
  analyzing: boolean;
  generatingNote: boolean;
}) {
  const { t } = useTranslation('admin');
  const canGenerateNote = doc.document_type === 'theory_book' && doc.analysis_status === 'analyzed';

  return (
    <Card>
      <CardContent className="flex flex-col gap-3 p-5">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div className="flex items-center gap-2">
            <FileText className="h-4 w-4 text-muted-foreground" />
            <span className="font-medium">{doc.title}</span>
            <Badge variant="outline">{t(`knowledgeLibrary.documentType${toPascalCase(doc.document_type)}`)}</Badge>
            {doc.year && <Badge variant="secondary">{doc.year}</Badge>}
            <Badge variant={STATUS_VARIANT[doc.analysis_status]}>{t(`knowledgeLibrary.status${toPascalCase(doc.analysis_status)}`)}</Badge>
          </div>
          <div className="flex gap-2">
            <Button size="sm" variant="outline" onClick={onAnalyze} disabled={analyzing}>
              {analyzing ? <InlineLoader className="h-3.5 w-3.5" /> : <FileSearch className="h-3.5 w-3.5" />}
              {' '}{t('knowledgeLibrary.analyze')}
            </Button>
            {canGenerateNote && (
              <Button size="sm" variant="outline" onClick={onGenerateNote} disabled={generatingNote}>
                {generatingNote ? <InlineLoader className="h-3.5 w-3.5" /> : <BookOpen className="h-3.5 w-3.5" />}
                {' '}{t('knowledgeLibrary.generateNote')}
              </Button>
            )}
            <Button size="sm" variant="ghost" onClick={onDelete}>
              <Trash2 className="h-3.5 w-3.5" />
            </Button>
          </div>
        </div>

        {doc.extracted_topics && doc.extracted_topics.length > 0 && (
          <div className="flex flex-col gap-1.5">
            <span className="text-xs font-medium text-muted-foreground">{t('knowledgeLibrary.matchedTopics')}</span>
            <div className="flex flex-wrap gap-1.5">
              {doc.extracted_topics.slice(0, 8).map((topic) => (
                <Badge key={topic.topic} variant="secondary" className="text-xs">
                  {topic.topic} ({topic.keyword_matches})
                </Badge>
              ))}
            </div>
          </div>
        )}

        {doc.extracted_theory_concepts && doc.extracted_theory_concepts.length > 0 && (
          <div className="flex flex-col gap-1.5">
            <span className="text-xs font-medium text-muted-foreground">{t('knowledgeLibrary.knowledgeMap')}</span>
            <div className="flex flex-col gap-1 rounded-md border border-border/60 p-2">
              {doc.extracted_theory_concepts.slice(0, 8).map((chapter, idx) => (
                <div key={idx} className="flex flex-wrap items-baseline gap-1.5 text-xs">
                  <span className="font-medium">{chapter.chapter}</span>
                  {chapter.topics.slice(0, 3).map((topic) => (
                    <Badge key={topic.topic} variant="outline" className="text-[10px]">
                      {topic.topic}
                    </Badge>
                  ))}
                </div>
              ))}
              {doc.extracted_theory_concepts.length > 8 && (
                <span className="text-[10px] text-muted-foreground">
                  +{doc.extracted_theory_concepts.length - 8} {t('knowledgeLibrary.moreChapters')}
                </span>
              )}
            </div>
          </div>
        )}

        {doc.reliability_note && (
          <p className="text-xs text-muted-foreground">
            <span className="font-medium">{t('knowledgeLibrary.reliabilityNote')}:</span> {doc.reliability_note}
          </p>
        )}
      </CardContent>
    </Card>
  );
}

function StudyNoteCard({
  note,
  onPublish,
  onReject,
  busy,
}: {
  note: StudyNote;
  onPublish: () => void;
  onReject: () => void;
  busy: boolean;
}) {
  const { t, i18n } = useTranslation('admin');
  const locale = i18n.language.startsWith('si') ? 'si' : 'en';

  return (
    <Card>
      <CardContent className="flex flex-col gap-3 p-5">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div className="flex items-center gap-2">
            <Badge variant={note.generation_method === 'gemini' ? 'default' : 'secondary'}>{note.generation_method}</Badge>
            {note.category && <Badge variant="outline">{locale === 'si' ? note.category.name_si : note.category.name_en}</Badge>}
          </div>
          <div className="flex gap-2">
            <Button size="sm" variant="outline" onClick={onReject} disabled={busy}>
              <X className="h-3.5 w-3.5" /> {t('aiQuestions.reject')}
            </Button>
            <Button size="sm" onClick={onPublish} disabled={busy}>
              <Check className="h-3.5 w-3.5" /> {t('knowledgeLibrary.publish')}
            </Button>
          </div>
        </div>
        <p className="font-medium">{locale === 'si' ? note.title_si : note.title_en}</p>
        <p className="text-sm text-muted-foreground">{locale === 'si' ? note.content_si : note.content_en}</p>
        {note.key_concepts && note.key_concepts.length > 0 && (
          <div className="flex flex-wrap gap-1.5">
            {note.key_concepts.map((concept) => (
              <Badge key={concept} variant="secondary" className="text-xs">
                {concept}
              </Badge>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}
