import { Component, type ReactNode } from 'react';

interface Props {
  children: ReactNode;
}

interface State {
  reference: string;
}

export class ErrorBoundary extends Component<Props, State> {
  state: State = { reference: '' };

  static getDerivedStateFromError(): State {
    return { reference: 'QN-UIERROR' };
  }

  componentDidCatch(): void {
    // The boundary shows an error reference. Raw stacks stay out of the console.
  }

  render(): ReactNode {
    if (this.state.reference !== '') {
      return (
        <div role="alert">
          <p>This part of QueryNova hit a problem.</p>
          <p>Error reference: {this.state.reference}</p>
          <button type="button" onClick={() => this.setState({ reference: '' })}>
            Retry
          </button>
        </div>
      );
    }
    return this.props.children;
  }
}
