import { motion, type Variants } from 'framer-motion';
import type { ReactNode } from 'react';

const fadeUp: Variants = {
  hidden: { opacity: 0, y: 16 },
  show: { opacity: 1, y: 0, transition: { duration: 0.5, ease: [0.16, 1, 0.3, 1] } },
};

const staggerContainer: Variants = {
  hidden: {},
  show: { transition: { staggerChildren: 0.08 } },
};

export function FadeIn({
  children,
  delay = 0,
  className,
}: {
  children: ReactNode;
  delay?: number;
  className?: string;
}) {
  return (
    <motion.div initial="hidden" animate="show" variants={fadeUp} transition={{ delay }} className={className}>
      {children}
    </motion.div>
  );
}

/** Wrap a group of FadeInItem children to fade them in one after another. */
export function FadeInStagger({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <motion.div initial="hidden" animate="show" variants={staggerContainer} className={className}>
      {children}
    </motion.div>
  );
}

export function FadeInItem({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <motion.div variants={fadeUp} className={className}>
      {children}
    </motion.div>
  );
}
