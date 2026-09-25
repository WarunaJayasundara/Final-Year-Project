import { Component, type ErrorInfo, type ReactNode } from 'react';
import { useLocation } from 'react-router-dom';
import { ErrorState } from '@/components/ui/error-state';
import i18n from '@/lib/i18n';

interface BoundaryProps {
  children: ReactNode;
  /** When this changes (the route did), a previous crash is cleared so the next page gets a fresh start. */
  resetKey: string;
}

interface BoundaryState {
  error: Error | null;
  /** The route the current state belongs to; a different one means the crash is over. */
  resetKey: string;
}

/** A lazily loaded page whose file could not be fetched (offline, or a new deployment replaced it). */
function isChunkLoadError(error: Error): boolean {
  return /dynamically imported module|Importing a module script failed|Loading chunk|ChunkLoadError/i.test(`${error.name} ${error.message}`);
}

class Boundary extends Component<BoundaryProps, BoundaryState> {
  state: BoundaryState = { error: null, resetKey: this.props.resetKey };

  static getDerivedStateFromError(error: Error): Partial<BoundaryState> {
    return { error };
  }

  static getDerivedStateFromProps(props: BoundaryProps, state: BoundaryState): Partial<BoundaryState> | null {
    return props.resetKey !== state.resetKey ? { error: null, resetKey: props.resetKey } : null;
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    console.error('Page crashed:', error, info.componentStack);
  }

  private retry = () => {
    const { error } = this.state;
    // A page that failed to download can only be recovered by fetching it again.
    if (error && isChunkLoadError(error)) {
      window.location.reload();
      return;
    }
    this.setState({ error: null });
  };

  render() {
    if (!this.state.error) return this.props.children;
    return (
      <div className="px-4 py-16">
        <ErrorState title={i18n.t('errors.generic', { ns: 'common' })} description={null} onRetry={this.retry} />
      </div>
    );
  }
}

/** Catches any error thrown while a page renders. */
export function RouteErrorBoundary({ children }: { children: ReactNode }) {
  const { pathname } = useLocation();
  return <Boundary resetKey={pathname}>{children}</Boundary>;
}
