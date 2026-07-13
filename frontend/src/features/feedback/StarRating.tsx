import { Star } from 'lucide-react';

export function StarRating({
  value,
  onChange,
  size = 'md',
}: {
  value: number;
  onChange: (value: number) => void;
  size?: 'sm' | 'md';
}) {
  const starClass = size === 'sm' ? 'h-4 w-4' : 'h-6 w-6';

  return (
    <div className="flex items-center gap-1">
      {[1, 2, 3, 4, 5].map((star) => (
        <button
          key={star}
          type="button"
          onClick={() => onChange(star)}
          aria-label={`${star} star`}
          className="rounded p-0.5 transition-colors hover:scale-110"
        >
          <Star className={`${starClass} ${star <= value ? 'fill-brand-gold text-brand-gold' : 'text-muted-foreground'}`} />
        </button>
      ))}
    </div>
  );
}
