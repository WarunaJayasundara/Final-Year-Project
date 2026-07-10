import { motion } from 'framer-motion';
import type { ComponentProps } from 'react';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

/** A Card that lifts and gains shadow on hover - used for interactive/clickable cards. */
export function MotionCard({ className, ...props }: ComponentProps<typeof Card>) {
  return (
    <motion.div
      whileHover={{ y: -4 }}
      transition={{ type: 'spring', stiffness: 300, damping: 24 }}
      className="h-full"
    >
      <Card className={cn('h-full transition-shadow duration-200 hover:shadow-lg hover:shadow-primary/10', className)} {...props} />
    </motion.div>
  );
}
