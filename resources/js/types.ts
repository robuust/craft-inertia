export interface PageProps {
  indexUrl: string;
  site: {
    name: string;
    language: string;
  };
}

export interface EntrySummary {
  id: number;
  title: string;
  url: string;
  postDate: string | null;
  summary: string;
}

export interface EntryDetail extends EntrySummary {
  body: string;
}
